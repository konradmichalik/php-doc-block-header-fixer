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
        /** @var array<string, string|array<string>|null> */
        public array $annotations,
        public bool $preserveExisting,
        public Separate $separate,
        public bool $addStructureName,
        public bool $ensureSpacing,
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
                'ensure_spacing' => $this->ensureSpacing,
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
    ): self {
        return new self(AnnotationService::normalize($annotations), $preserveExisting, $separate, $addStructureName, $ensureSpacing);
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

        return self::create($annotations, $preserveExisting, $separate, $addStructureName, $ensureSpacing);
    }
}
