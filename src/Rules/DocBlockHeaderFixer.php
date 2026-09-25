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
use PhpCsFixer\Fixer\{ConfigurableFixerInterface, WhitespacesAwareFixerInterface};
use PhpCsFixer\FixerConfiguration\{FixerConfigurationResolver, FixerConfigurationResolverInterface, FixerOptionBuilder};
use PhpCsFixer\FixerDefinition\{FixerDefinition, FixerDefinitionInterface};
use PhpCsFixer\Tokenizer\{Token, Tokens};
use SplFileInfo;

use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * DocBlockHeaderFixer.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 *
 * @implements ConfigurableFixerInterface<array<string, mixed>, array<string, mixed>>
 */
final class DocBlockHeaderFixer extends AbstractFixer implements ConfigurableFixerInterface, WhitespacesAwareFixerInterface
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

        // Walk backwards so an insertion never shifts a structure still to be visited.
        for ($index = $tokens->count() - 1; $index >= 0; --$index) {
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

            // Skip comments and modifiers that can appear between 'new' and 'class'
            if ($token->isGivenKind([\T_COMMENT, \T_FINAL, \T_READONLY])) {
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

            // A comment says nothing about whether a DocBlock exists, so it must
            // not end the search. findInsertPosition() deliberately differs.
            // If we hit any other meaningful token (except modifiers), stop looking
            if (!$token->isGivenKind([\T_COMMENT, \T_FINAL, \T_ABSTRACT, \T_READONLY])) {
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
            $newDocBlock = $this->buildDocBlock($mergedAnnotations, $structureName, $this->getIndentBefore($tokens, $docBlockIndex));
        }

        $tokens[$docBlockIndex] = new Token([\T_DOC_COMMENT, $newDocBlock]);

        // Ensure there's proper spacing after existing DocBlock
        $ensureSpacing = $this->resolvedConfiguration['ensure_spacing'] ?? true;
        if ($ensureSpacing) {
            $this->ensureProperSpacingAfterDocBlock($tokens, $docBlockIndex);
        }
    }

    /**
     * Injects the configured header annotations into an existing multi-line DocBlock
     * without rebuilding it, so unknown content is passed through unchanged.
     *
     * Configured annotations are enforced: an existing occurrence is rewritten in
     * place and further occurrences of the same tag are dropped. Everything the
     * configuration does not mention (descriptions, other tags, ordering) survives
     * verbatim.
     *
     * A T_DOC_COMMENT always opens on its first and closes on its last line. Both
     * are left untouched, so an annotation squeezed onto one of them is not
     * enforced; rewriting it would have to drop the "/**" or "*\/" with it.
     *
     * @param array<string, string|array<string>|null> $annotations
     */
    private function injectHeaderAnnotations(string $existingContent, array $annotations, string $structureName): string
    {
        // Keep the DocBlock's own line ending, so injected lines don't mix CRLF and LF.
        $lineEnding = str_contains($existingContent, "\r\n") ? "\r\n" : "\n";
        $lines = explode($lineEnding, $existingContent);

        // Derive the indentation used for the DocBlock's " * " lines.
        $indent = '';
        foreach ($lines as $line) {
            if (preg_match('/^(\s*)\*/', $line, $matches)) {
                $indent = $matches[1];
                break;
            }
        }
        $prefix = $indent.'* ';

        // Enforce every configured annotation in place and collect the ones that
        // are absent, so they can be appended before the closing "*/".
        $linesToAdd = [];
        foreach ($annotations as $tag => $value) {
            $annotationLines = $this->formatAnnotationLines($tag, $value, $prefix);
            [$lines, $replaced] = $this->replaceAnnotationLines($lines, $tag, $annotationLines);

            if (!$replaced) {
                foreach ($annotationLines as $annotationLine) {
                    $linesToAdd[] = $annotationLine;
                }
            }
        }

        $addStructureName = $this->resolvedConfiguration['add_structure_name'] ?? false;
        if ($addStructureName && '' !== $structureName) {
            $lines = $this->applyStructureNameSummary($lines, $structureName, $indent, $prefix);
        }

        if ([] !== $linesToAdd) {
            array_splice($lines, count($lines) - 1, 0, $linesToAdd);
        }

        return implode($lineEnding, $lines);
    }

    /**
     * Rewrites the first occurrence of an annotation in place and drops any further
     * occurrence of the same tag, including the continuation lines of its value.
     *
     * @param array<string> $lines
     * @param array<string> $annotationLines
     *
     * @return array{array<string>, bool}
     */
    private function replaceAnnotationLines(array $lines, string $tag, array $annotationLines): array
    {
        $pattern = '/^@'.preg_quote($tag, '/').'(?:\s|$)/';
        $lastIndex = count($lines) - 1;
        $result = [];
        $replaced = false;
        $dropping = false;

        foreach ($lines as $index => $line) {
            $isBoundary = 0 === $index || $index === $lastIndex;
            $content = $isBoundary ? '' : trim($line, " \t\r\n/*");

            if ($dropping) {
                if ('' !== $content && !str_starts_with($content, '@')) {
                    continue;
                }
                $dropping = false;
            }

            if ('' !== $content && preg_match($pattern, $content)) {
                $dropping = true;

                if (!$replaced) {
                    $replaced = true;
                    foreach ($annotationLines as $annotationLine) {
                        $result[] = $annotationLine;
                    }
                }

                continue;
            }

            $result[] = $line;
        }

        return [$result, $replaced];
    }

    /**
     * Rewrites a stale structure name summary or prepends a missing one.
     *
     * @param array<string> $lines
     *
     * @return array<string>
     */
    private function applyStructureNameSummary(array $lines, string $structureName, string $indent, string $prefix): array
    {
        if ($this->hasStructureNameSummary($lines, $structureName)) {
            return $lines;
        }

        $summaryLine = rtrim($prefix.$structureName.'.');
        $slotIndex = 1;

        // A bare identifier followed by a dot occupies the structure name slot and
        // belongs to a former name, so it is rewritten instead of pushed down.
        if ($slotIndex < count($lines) - 1 && $this->isStructureNameSlot($lines[$slotIndex])) {
            $lines[$slotIndex] = $summaryLine;

            return $lines;
        }

        array_splice($lines, $slotIndex, 0, [$summaryLine, rtrim($indent.'*')]);

        return $lines;
    }

    private function isStructureNameSlot(string $line): bool
    {
        return 1 === preg_match('/^[A-Za-z_]\w*\.$/', trim($line, " \t\r\n/*"));
    }

    /**
     * @param string|array<string>|null $value
     *
     * @return array<string>
     */
    private function formatAnnotationLines(string $tag, string|array|null $value, string $prefix): array
    {
        if (is_array($value) && [] !== $value) {
            $lines = [];
            foreach ($value as $singleValue) {
                $lines[] = rtrim($prefix.'@'.$tag.('' !== $singleValue ? ' '.$singleValue : ''));
            }

            return $lines;
        }

        // null and an empty list both stand for a bare tag
        $value = is_string($value) ? $value : '';

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
        $newDocBlock = $this->buildDocBlock($annotations, $structureName, $this->getIndentBefore($tokens, $docBlockIndex));
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

        // The structure always starts on its own line, a blank line is only added on request.
        // Both go into one whitespace token, since rules like no_blank_lines_after_phpdoc
        // only inspect a single token.
        $lineEnding = $this->whitespacesConfig->getLineEnding();
        $indent = $this->getIndentBefore($tokens, $insertIndex);
        $whitespaceAfter = str_repeat($lineEnding, in_array($separate, ['bottom', 'both'], true) ? 2 : 1).$indent;

        $tokens->insertAt($insertIndex, [
            new Token([\T_DOC_COMMENT, $this->buildDocBlock($annotations, $structureName, $indent)]),
            new Token([\T_WHITESPACE, $whitespaceAfter]),
        ]);

        if (in_array($separate, ['top', 'both'], true)) {
            $this->ensureBlankLineBefore($tokens, $insertIndex);
        }
    }

    /**
     * Reshapes the whitespace before the token into exactly one blank line instead of
     * adding a second whitespace token next to it.
     */
    private function ensureBlankLineBefore(Tokens $tokens, int $index): void
    {
        $lineEnding = $this->whitespacesConfig->getLineEnding();

        $tokens->ensureWhitespaceAtIndex($index - 1, 1, $lineEnding.$lineEnding.$this->getIndentBefore($tokens, $index));
    }

    /**
     * Returns the indentation of the line the token starts on, or '' if it shares
     * the line with other code.
     */
    private function getIndentBefore(Tokens $tokens, int $index): string
    {
        $previous = $tokens[$index - 1];
        if (!$previous->isWhitespace()) {
            return '';
        }

        $whitespace = $previous->getContent();
        $lastNewline = strrpos($whitespace, "\n");

        return false === $lastNewline ? '' : substr($whitespace, $lastNewline + 1);
    }

    /**
     * Comments terminate the backward walk on purpose: a preceding comment is
     * indistinguishable from a file header comment, and a new DocBlock must not
     * be placed above such a header.
     */
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
     * @param array<string, string|array<string>|null> $annotations
     */
    private function buildDocBlock(array $annotations, string $structureName, string $indent = ''): string
    {
        $prefix = ' * ';
        $lines = ['/**'];

        $addStructureName = $this->resolvedConfiguration['add_structure_name'] ?? false;
        if ($addStructureName && '' !== $structureName) {
            $lines[] = $prefix.$structureName.'.';

            // Blank line after the structure name, compatible with phpdoc_separation
            if ([] !== $annotations) {
                $lines[] = ' *';
            }
        }

        foreach ($annotations as $tag => $value) {
            array_push($lines, ...$this->formatAnnotationLines($tag, $value, $prefix));
        }

        $lines[] = ' */';

        return implode($this->whitespacesConfig->getLineEnding().$indent, $lines);
    }

    /**
     * Ensures proper spacing after a DocBlock to prevent conflicts with PHP-CS-Fixer rules.
     */
    private function ensureProperSpacingAfterDocBlock(Tokens $tokens, int $docBlockIndex): void
    {
        $nextIndex = $docBlockIndex + 1;
        if (!$tokens->offsetExists($nextIndex)) {
            return;
        }

        $nextToken = $tokens[$nextIndex];
        if ($nextToken->isWhitespace() && str_contains($nextToken->getContent(), "\n")) {
            return;
        }

        // Replaces a same-line space, so the structure does not keep it as indentation.
        $tokens->ensureWhitespaceAtIndex($nextIndex, 0, $this->whitespacesConfig->getLineEnding().$this->getIndentBefore($tokens, $docBlockIndex));
    }
}
