<?php

declare(strict_types=1);

/*
 * This file is part of the "php-doc-block-header-fixer" Composer package.
 *
 * (c) Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\PhpDocBlockHeaderFixer\Rules;

use KonradMichalik\PhpDocBlockHeaderFixer\Enum\Separate;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\FixerConfiguration\{FixerConfigurationResolver, FixerConfigurationResolverInterface, FixerOptionBuilder};
use PhpCsFixer\FixerDefinition\{FixerDefinition, FixerDefinitionInterface};
use PhpCsFixer\Tokenizer\{Token, Tokens};
use SplFileInfo;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;

/**
 * DocBlockHeaderFixer.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 *
 * @implements ConfigurableFixerInterface<array<string, mixed>, array<string, mixed>>
 */
final class DocBlockHeaderFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $resolvedConfiguration = [];

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Add configurable DocBlock annotations before class, interface, trait, and enum declarations.',
            [],
        );
    }

    public function getName(): string
    {
        return 'KonradMichalik/docblock_header_comment';
    }

    public function getPriority(): int
    {
        // Run before single_line_after_imports (0) and no_extra_blank_lines (0)
        // Higher priority values run first
        return 1;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(\T_CLASS)
            || $tokens->isTokenKindFound(\T_INTERFACE)
            || $tokens->isTokenKindFound(\T_TRAIT)
            || $tokens->isTokenKindFound(\T_ENUM);
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('annotations', 'DocBlock annotations to add'))
                ->setAllowedTypes(['array'])
                ->setDefault([])
                ->getOption(),
            (new FixerOptionBuilder('preserve_existing', 'Preserve existing DocBlock annotations'))
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
            (new FixerOptionBuilder('separate', 'Separate the comment'))
                ->setAllowedValues(Separate::getList())
                ->setDefault(Separate::None->value)
                ->getOption(),
            (new FixerOptionBuilder('add_structure_name', 'Add structure name before annotations'))
                ->setAllowedTypes(['bool'])
                ->setDefault(false)
                ->getOption(),
            (new FixerOptionBuilder('ensure_spacing', 'Ensure proper spacing after DocBlock to prevent conflicts with PHP-CS-Fixer rules'))
                ->setAllowedTypes(['bool'])
                ->setDefault(true)
                ->getOption(),
        ]);
    }

    public function configure(?array $configuration = null): void
    {
        $this->resolvedConfiguration = $this->getConfigurationDefinition()->resolve($configuration ?? []);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $annotations = $this->resolvedConfiguration['annotations'] ?? [];
        if (empty($annotations)) {
            return;
        }

        for ($index = 0, $limit = $tokens->count(); $index < $limit; ++$index) {
            $token = $tokens[$index];

            if (!$token->isGivenKind([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM])) {
                continue;
            }

            // Skip anonymous classes (preceded by 'new' keyword)
            if ($token->isGivenKind(\T_CLASS) && $this->isAnonymousClass($tokens, $index)) {
                continue;
            }

            $structureName = $this->getStructureName($tokens, $index);
            $this->processStructureDocBlock($tokens, $index, $annotations, $structureName);
        }
    }

    private function isAnonymousClass(Tokens $tokens, int $classIndex): bool
    {
        // Look backwards for 'new' keyword
        $insideAttribute = false;
        for ($i = $classIndex - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            // Skip whitespace
            if ($token->isWhitespace()) {
                continue;
            }

            // When going backwards, ']' marks the end of an attribute (we enter it)
            if (']' === $token->getContent()) {
                $insideAttribute = true;
                continue;
            }

            // T_ATTRIBUTE '#[' marks the start of an attribute (we exit it when going backwards)
            if ($token->isGivenKind(\T_ATTRIBUTE)) {
                $insideAttribute = false;
                continue;
            }

            // Skip everything inside attributes
            if ($insideAttribute) {
                continue;
            }

            // Skip modifiers that can appear between 'new' and 'class'
            if ($token->isGivenKind([\T_FINAL, \T_READONLY])) {
                continue;
            }

            // If we find 'new', it's an anonymous class
            if ($token->isGivenKind(\T_NEW)) {
                return true;
            }

            // If we hit any other meaningful token, it's not anonymous
            break;
        }

        return false;
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    private function processStructureDocBlock(Tokens $tokens, int $structureIndex, array $annotations, string $structureName): void
    {
        $existingDocBlockIndex = $this->findExistingDocBlock($tokens, $structureIndex);
        $preserveExisting = $this->resolvedConfiguration['preserve_existing'] ?? true;

        if (null !== $existingDocBlockIndex) {
            if ($preserveExisting) {
                $this->mergeWithExistingDocBlock($tokens, $existingDocBlockIndex, $annotations, $structureName);
            } else {
                $this->replaceDocBlock($tokens, $existingDocBlockIndex, $annotations, $structureName);
            }
        } else {
            $this->insertNewDocBlock($tokens, $structureIndex, $annotations, $structureName);
        }
    }

    private function getStructureName(Tokens $tokens, int $structureIndex): string
    {
        // Look for the structure name token after the keyword (class/interface/trait/enum)
        for ($i = $structureIndex + 1, $limit = $tokens->count(); $i < $limit; ++$i) {
            $token = $tokens[$i];

            if ($token->isWhitespace()) {
                continue;
            }

            // The first non-whitespace token after the keyword should be the structure name
            if ($token->isGivenKind(\T_STRING)) {
                return $token->getContent();
            }

            // If we hit anything else, stop looking
            break;
        }

        return '';
    }

    private function findExistingDocBlock(Tokens $tokens, int $structureIndex): ?int
    {
        $insideAttribute = false;

        for ($i = $structureIndex - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            if ($token->isWhitespace()) {
                continue;
            }

            // When going backwards, ']' marks the end of an attribute (we enter it)
            if (']' === $token->getContent()) {
                $insideAttribute = true;
                continue;
            }

            // T_ATTRIBUTE '#[' marks the start of an attribute (we exit it when going backwards)
            if ($token->isGivenKind(\T_ATTRIBUTE)) {
                $insideAttribute = false;
                continue;
            }

            // Skip everything inside attributes
            if ($insideAttribute) {
                continue;
            }

            if ($token->isGivenKind(\T_DOC_COMMENT)) {
                return $i;
            }

            // If we hit any other meaningful token (except modifiers), stop looking
            if (!$token->isGivenKind([\T_FINAL, \T_ABSTRACT, \T_READONLY])) {
                break;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    private function mergeWithExistingDocBlock(Tokens $tokens, int $docBlockIndex, array $annotations, string $structureName): void
    {
        $existingContent = $tokens[$docBlockIndex]->getContent();

        // Surgically inject only the configured header annotations while keeping
        // every existing line verbatim (hyphenated tags, multi-line tag values,
        // free-text descriptions). Degenerate single-line DocBlocks that cannot be
        // amended in place fall back to a parse + rebuild.
        if (str_contains($existingContent, "\n")) {
            $newDocBlock = $this->injectHeaderAnnotations($existingContent, $annotations, $structureName);
        } else {
            $mergedAnnotations = $this->mergeAnnotations($this->parseExistingAnnotations($existingContent), $annotations);
            $newDocBlock = $this->buildDocBlock($mergedAnnotations, $structureName);
        }

        $tokens[$docBlockIndex] = new Token([\T_DOC_COMMENT, $newDocBlock]);

        // Ensure there's proper spacing after existing DocBlock
        $ensureSpacing = $this->resolvedConfiguration['ensure_spacing'] ?? true;
        if ($ensureSpacing) {
            $this->ensureProperSpacingAfterDocBlock($tokens, $docBlockIndex);
        }
    }

    /**
     * Inserts the configured header annotations into an existing multi-line DocBlock
     * without rebuilding it, so unknown content is passed through unchanged.
     *
     * @param array<string, string|array<string>|null> $annotations
     */
    private function injectHeaderAnnotations(string $existingContent, array $annotations, string $structureName): string
    {
        $lines = explode("\n", $existingContent);

        // Derive the indentation used for the DocBlock's " * " lines.
        $indent = '';
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)\*/', $line, $matches)) {
                $indent = $matches[1];
                break;
            }
        }
        $prefix = $indent.'* ';

        $existingTags = $this->parseExistingAnnotations($existingContent);

        // Only add configured header annotations whose tag is not already present;
        // existing annotations win and are never overwritten in preserve mode.
        $linesToAdd = [];
        foreach ($annotations as $tag => $value) {
            if (array_key_exists($tag, $existingTags)) {
                continue;
            }
            foreach ($this->formatAnnotationLines($tag, $value, $prefix) as $annotationLine) {
                $linesToAdd[] = $annotationLine;
            }
        }

        // Optionally prepend the structure name summary if it is not already there.
        $summaryLines = [];
        $addStructureName = $this->resolvedConfiguration['add_structure_name'] ?? false;
        if ($addStructureName && '' !== $structureName && !$this->hasStructureNameSummary($lines, $structureName)) {
            $summaryLines[] = rtrim($prefix.$structureName.'.');
            $summaryLines[] = rtrim($indent.'*');
        }

        // Locate the opening "/**" and closing "*/" lines.
        $openingIndex = 0;
        foreach ($lines as $i => $line) {
            if (str_contains($line, '/**')) {
                $openingIndex = $i;
                break;
            }
        }
        $closingIndex = count($lines) - 1;
        for ($i = count($lines) - 1; $i >= 0; --$i) {
            if (str_contains($lines[$i], '*/')) {
                $closingIndex = $i;
                break;
            }
        }

        // Insert the summary right after the opening line, tags right before "*/".
        if ([] !== $summaryLines) {
            array_splice($lines, $openingIndex + 1, 0, $summaryLines);
            $closingIndex += count($summaryLines);
        }
        if ([] !== $linesToAdd) {
            array_splice($lines, $closingIndex, 0, $linesToAdd);
        }

        return implode("\n", $lines);
    }

    /**
     * @param string|array<string>|null $value
     *
     * @return array<string>
     */
    private function formatAnnotationLines(string $tag, string|array|null $value, string $prefix): array
    {
        if (is_array($value)) {
            $lines = [];
            foreach ($value as $singleValue) {
                $lines[] = rtrim($prefix.'@'.$tag.('' !== $singleValue ? ' '.$singleValue : ''));
            }

            return $lines;
        }

        $value ??= '';

        return [rtrim($prefix.'@'.$tag.('' !== $value ? ' '.$value : ''))];
    }

    /**
     * @param array<string> $lines
     */
    private function hasStructureNameSummary(array $lines, string $structureName): bool
    {
        $needle = $structureName.'.';
        foreach ($lines as $line) {
            if (trim($line, " \t\r\n/*") === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    private function replaceDocBlock(Tokens $tokens, int $docBlockIndex, array $annotations, string $structureName): void
    {
        $newDocBlock = $this->buildDocBlock($annotations, $structureName);
        $tokens[$docBlockIndex] = new Token([\T_DOC_COMMENT, $newDocBlock]);

        // Ensure there's proper spacing after replaced DocBlock
        $ensureSpacing = $this->resolvedConfiguration['ensure_spacing'] ?? true;
        if ($ensureSpacing) {
            $this->ensureProperSpacingAfterDocBlock($tokens, $docBlockIndex);
        }
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    private function insertNewDocBlock(Tokens $tokens, int $structureIndex, array $annotations, string $structureName): void
    {
        $separate = $this->resolvedConfiguration['separate'] ?? 'none';
        $insertIndex = $this->findInsertPosition($tokens, $structureIndex);

        $tokensToInsert = [];

        // Add separation before comment if needed
        if (in_array($separate, ['top', 'both'], true)) {
            $tokensToInsert[] = new Token([\T_WHITESPACE, "\n"]);
        }

        // Add the DocBlock
        $docBlock = $this->buildDocBlock($annotations, $structureName);
        $tokensToInsert[] = new Token([\T_DOC_COMMENT, $docBlock]);

        // Add a newline after the DocBlock if ensure_spacing is enabled (default)
        // This prevents conflicts with single_line_after_imports and no_extra_blank_lines rules
        $ensureSpacing = $this->resolvedConfiguration['ensure_spacing'] ?? true;
        if ($ensureSpacing) {
            $tokensToInsert[] = new Token([\T_WHITESPACE, "\n"]);
        }

        // Add additional separation if configured
        if (in_array($separate, ['bottom', 'both'], true)) {
            // Check if there's already whitespace after the structure declaration
            $nextToken = $tokens[$structureIndex] ?? null;
            if (null !== $nextToken && !$nextToken->isWhitespace()) {
                $tokensToInsert[] = new Token([\T_WHITESPACE, "\n"]);
            }
        }

        $tokens->insertAt($insertIndex, $tokensToInsert);
    }

    private function findInsertPosition(Tokens $tokens, int $structureIndex): int
    {
        $insertIndex = $structureIndex;
        $insideAttribute = false;

        // Look backwards for attributes, final, abstract keywords
        for ($i = $structureIndex - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            if ($token->isWhitespace()) {
                continue;
            }

            // When going backwards, ']' marks the end of an attribute (we enter it)
            if (']' === $token->getContent()) {
                $insideAttribute = true;
                continue;
            }

            // T_ATTRIBUTE '#[' marks the start of an attribute (we exit it when going backwards)
            if ($token->isGivenKind(\T_ATTRIBUTE)) {
                $insideAttribute = false;
                $insertIndex = $i;
                continue;
            }

            // Skip everything inside attributes
            if ($insideAttribute) {
                continue;
            }

            if ($token->isGivenKind([\T_FINAL, \T_ABSTRACT, \T_READONLY])) {
                $insertIndex = $i;
                continue;
            }

            break;
        }

        return $insertIndex;
    }

    /**
     * @return array<string, string|array<string>>
     */
    private function parseExistingAnnotations(string $docBlockContent): array
    {
        $lines = explode("\n", $docBlockContent);
        $annotations = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\r\n/*");
            if (preg_match('/^@([a-zA-Z][\w-]*)(?:\s+(.*))?$/', $line, $matches)) {
                $tag = $matches[1];
                $value = $matches[2] ?? '';

                // If this tag already exists, convert to array or append to existing array
                if (isset($annotations[$tag])) {
                    // Convert existing single value to array
                    if (!is_array($annotations[$tag])) {
                        $annotations[$tag] = [$annotations[$tag]];
                    }
                    // Append new value to array
                    $annotations[$tag][] = $value;
                } else {
                    // First occurrence, store as string
                    $annotations[$tag] = $value;
                }
            }
        }

        return $annotations;
    }

    /**
     * @param array<string, string|array<string>> $existing
     * @param array<string, string|array<string>> $new
     *
     * @return array<string, string|array<string>>
     */
    private function mergeAnnotations(array $existing, array $new): array
    {
        // New annotations take precedence, but we keep existing ones that aren't being overridden
        return array_merge($existing, $new);
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    private function buildDocBlock(array $annotations, string $structureName): string
    {
        $addStructureName = $this->resolvedConfiguration['add_structure_name'] ?? false;

        if (empty($annotations) && !$addStructureName) {
            return "/**\n */";
        }

        $docBlock = "/**\n";

        // Add structure name with dot if configured
        if ($addStructureName && !empty($structureName)) {
            $docBlock .= " * {$structureName}.\n";

            // Add empty line after structure name if there are annotations - compatible with phpdoc_separation
            if (!empty($annotations)) {
                $docBlock .= " *\n";
            }
        }

        foreach ($annotations as $tag => $value) {
            if (empty($value)) {
                $docBlock .= " * @{$tag}\n";
            } elseif (is_array($value)) {
                // Handle multiple values for the same tag (e.g., multiple authors)
                foreach ($value as $singleValue) {
                    $docBlock .= " * @{$tag} {$singleValue}\n";
                }
            } else {
                $docBlock .= " * @{$tag} {$value}\n";
            }
        }

        $docBlock .= ' */';

        return $docBlock;
    }

    /**
     * Ensures proper spacing after a DocBlock to prevent conflicts with PHP-CS-Fixer rules.
     */
    private function ensureProperSpacingAfterDocBlock(Tokens $tokens, int $docBlockIndex): void
    {
        $nextIndex = $docBlockIndex + 1;

        // Check if the next token exists and is not already a newline
        if ($nextIndex < $tokens->count()) {
            $nextToken = $tokens[$nextIndex];

            // If the next token is not whitespace or doesn't contain a newline, add one
            if (!$nextToken->isWhitespace() || !str_contains($nextToken->getContent(), "\n")) {
                // Insert a newline token after the DocBlock
                $tokens->insertAt($nextIndex, [new Token([\T_WHITESPACE, "\n"])]);
            }
        }
    }
}
