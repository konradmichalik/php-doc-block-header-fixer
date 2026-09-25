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

namespace KonradMichalik\PhpDocBlockHeaderFixer\Generators;

use InvalidArgumentException;
use KonradMichalik\PhpDocBlockHeaderFixer\Enum\Separate;
use KonradMichalik\PhpDocBlockHeaderFixer\Service\ComposerService;

use function count;
use function gettype;
use function in_array;
use function is_string;
use function sprintf;

/**
 * DocBlockHeader.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final readonly class DocBlockHeader implements Generator
{
    private function __construct(
        /** @var array<string, string|array<string>> */
        public array $annotations,
        public bool $preserveExisting,
        public Separate $separate,
        public bool $addStructureName,
        public bool $replaceStaleStructureName,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function __toArray(): array
    {
        return [
            'KonradMichalik/docblock_header_comment' => [
                'annotations' => $this->annotations,
                'preserve_existing' => $this->preserveExisting,
                'separate' => $this->separate->value,
                'add_structure_name' => $this->addStructureName,
                'replace_stale_structure_name' => $this->replaceStaleStructureName,
            ],
        ];
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    public static function create(
        array $annotations,
        bool $preserveExisting = true,
        Separate $separate = Separate::Both,
        bool $addStructureName = true,
        bool $replaceStaleStructureName = false,
    ): self {
        self::validateAnnotations($annotations);

        return new self($annotations, $preserveExisting, $separate, $addStructureName, $replaceStaleStructureName);
    }

    /**
     * @param array<string, string|array<string>> $additionalAnnotations
     */
    public static function fromComposer(
        string $composerJsonPath = 'composer.json',
        array $additionalAnnotations = [],
        bool $preserveExisting = true,
        Separate $separate = Separate::Both,
        bool $addStructureName = true,
        bool $replaceStaleStructureName = false,
    ): self {
        $composerData = ComposerService::readComposerJson($composerJsonPath);

        $annotations = [];

        $authors = ComposerService::extractAuthors($composerData);
        if (!empty($authors)) {
            if (count($authors) > 1) {
                $authorStrings = [];
                foreach ($authors as $author) {
                    $authorString = $author['name'];
                    if (isset($author['email'])) {
                        $authorString .= sprintf(' <%s>', $author['email']);
                    }
                    $authorStrings[] = $authorString;
                }
                $annotations['author'] = $authorStrings;
            } else {
                $primaryAuthor = $authors[0];
                $authorString = $primaryAuthor['name'];
                if (isset($primaryAuthor['email'])) {
                    $authorString .= sprintf(' <%s>', $primaryAuthor['email']);
                }
                $annotations['author'] = $authorString;
            }
        }

        $license = ComposerService::extractLicense($composerData);
        if (null !== $license) {
            $annotations['license'] = $license;
        }

        $annotations = [...$annotations, ...$additionalAnnotations];

        return self::create($annotations, $preserveExisting, $separate, $addStructureName, $replaceStaleStructureName);
    }

    /**
     * @param array<string, string|array<string>> $annotations
     */
    private static function validateAnnotations(array $annotations): void
    {
        $allowedAnnotations = [
            'author', 'copyright', 'license', 'version', 'since', 'package', 'subpackage',
            'see', 'link', 'todo', 'fixme', 'deprecated', 'internal', 'api', 'category',
            'example', 'ignore', 'uses', 'used-by', 'throws', 'method', 'property',
            'property-read', 'property-write', 'param', 'return', 'var', 'global',
            'static', 'final', 'abstract',
        ];

        foreach ($annotations as $key => $value) {
            // PHPStan knows $key is string from PHPDoc, but we still validate at runtime
            /* @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf('Annotation key must be a string, %s given', gettype($key)));
            }

            if (empty(trim($key))) {
                throw new InvalidArgumentException('Annotation key cannot be empty');
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $key)) {
                throw new InvalidArgumentException(sprintf('Invalid annotation key "%s". Must start with letter and contain only letters, numbers, underscore, or dash.', $key));
            }

            if (!in_array($key, $allowedAnnotations, true)) {
                throw new InvalidArgumentException(sprintf('Unknown annotation "%s". Allowed annotations: %s', $key, implode(', ', $allowedAnnotations)));
            }
        }
    }
}
