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
    ) {}

    /**
     * @deprecated use toArray() instead, the "__" prefix is reserved for PHP magic methods
     *
     * @return array<string, array<string, mixed>>
     */
    public function __toArray(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return [
            'KonradMichalik/docblock_header_comment' => [
                'annotations' => $this->annotations,
                'preserve_existing' => $this->preserveExisting,
                'separate' => $this->separate->value,
                'add_structure_name' => $this->addStructureName,
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
    ): self {
        self::validateAnnotations($annotations);

        return new self($annotations, $preserveExisting, $separate, $addStructureName);
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
    ): self {
        $composerData = ComposerService::readComposerJson($composerJsonPath);

        $annotations = [];

        $authors = array_map(
            static fn (array $author): string => isset($author['email']) ? sprintf('%s <%s>', $author['name'], $author['email']) : $author['name'],
            ComposerService::extractAuthors($composerData),
        );
        if ([] !== $authors) {
            $annotations['author'] = 1 === count($authors) ? $authors[0] : $authors;
        }

        $license = ComposerService::extractLicense($composerData);
        if (null !== $license) {
            $annotations['license'] = $license;
        }

        $annotations = [...$annotations, ...$additionalAnnotations];

        return self::create($annotations, $preserveExisting, $separate, $addStructureName);
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
