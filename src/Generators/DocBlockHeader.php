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

use KonradMichalik\PhpDocBlockHeaderFixer\Enum\Separate;
use KonradMichalik\PhpDocBlockHeaderFixer\Service\{AnnotationService, ComposerService};

use function count;
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
        /** @var array<string, string|list<string>|array{value: string|list<string>|null, strategy: string}|null> */
        public array $annotations,
        public bool $preserveExisting,
        public Separate $separate,
        public bool $addStructureName,
        public bool $ensureSpacing,
        public bool $replaceStaleStructureName,
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
                'ensure_spacing' => $this->ensureSpacing,
                'replace_stale_structure_name' => $this->replaceStaleStructureName,
            ],
        ];
    }

    /**
     * @param array<string, string|int|float|list<string|int|float>|null> $annotations
     */
    public static function create(
        array $annotations,
        bool $preserveExisting = true,
        Separate $separate = Separate::None,
        bool $addStructureName = false,
        bool $ensureSpacing = true,
        bool $replaceStaleStructureName = false,
    ): self {
        return new self(AnnotationService::normalize($annotations), $preserveExisting, $separate, $addStructureName, $ensureSpacing, $replaceStaleStructureName);
    }

    /**
     * @param array<string, string|int|float|list<string|int|float>|null> $additionalAnnotations
     */
    public static function fromComposer(
        string $composerJsonPath = 'composer.json',
        array $additionalAnnotations = [],
        bool $preserveExisting = true,
        Separate $separate = Separate::None,
        bool $addStructureName = false,
        bool $ensureSpacing = true,
        bool $replaceStaleStructureName = false,
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

        return self::create($annotations, $preserveExisting, $separate, $addStructureName, $ensureSpacing, $replaceStaleStructureName);
    }
}
