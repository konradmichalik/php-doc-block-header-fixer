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

namespace KonradMichalik\PhpDocBlockHeaderFixer\Service;

use RuntimeException;

use function count;
use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function sprintf;

/**
 * ComposerService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class ComposerService
{
    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public static function readComposerJson(string $composerJsonPath = 'composer.json'): array
    {
        if (!file_exists($composerJsonPath)) {
            throw new RuntimeException(sprintf('The file "%s" does not exist.', $composerJsonPath));
        }

        $contents = file_get_contents($composerJsonPath);

        if (false === $contents) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $composerJsonPath));
        }

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Unable to decode JSON from file "%s".', $composerJsonPath));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $composerData
     */
    public static function extractLicense(array $composerData): ?string
    {
        if (!isset($composerData['license'])) {
            return null;
        }

        $license = $composerData['license'];

        if (!is_array($license)) {
            return $license;
        }

        $licenses = array_values($license);

        return match (count($licenses)) {
            0 => null,
            1 => $licenses[0],
            default => '('.implode(' or ', $licenses).')',
        };
    }

    /**
     * @param array<string, mixed> $composerData
     *
     * @return list<array{name: string, email?: string, role?: string}>
     */
    public static function extractAuthors(array $composerData): array
    {
        if (!isset($composerData['authors']) || !is_array($composerData['authors'])) {
            return [];
        }

        $authors = [];

        foreach ($composerData['authors'] as $author) {
            if (!is_array($author) || !isset($author['name'])) {
                continue;
            }

            $authors[] = $author;
        }

        return $authors;
    }

    /**
     * @param array<string, mixed> $composerData
     *
     * @return array{name: string, email?: string, role?: string}|null
     */
    public static function getPrimaryAuthor(array $composerData): ?array
    {
        $authors = self::extractAuthors($composerData);

        return $authors[0] ?? null;
    }
}
