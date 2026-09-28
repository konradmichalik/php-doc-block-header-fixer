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

use InvalidArgumentException;

use function get_debug_type;
use function gettype;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * AnnotationService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class AnnotationService
{
    /**
     * Validates annotation tags and values and casts numeric values to strings.
     *
     * @param array<mixed> $annotations
     *
     * @return array<string, string|array<string>|null>
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(array $annotations): array
    {
        $normalized = [];

        foreach ($annotations as $tag => $value) {
            self::validateTag($tag);

            if (null === $value) {
                $normalized[$tag] = null;
                continue;
            }

            $normalized[$tag] = is_array($value)
                ? array_map(static fn (mixed $singleValue): string => self::normalizeString($tag, $singleValue), $value)
                : self::normalizeString($tag, $value);
        }

        return $normalized;
    }

    /**
     * @phpstan-assert string $tag
     */
    private static function validateTag(mixed $tag): void
    {
        if (!is_string($tag)) {
            throw new InvalidArgumentException(sprintf('Annotation key must be a string, %s given', gettype($tag)));
        }

        if ('' === trim($tag)) {
            throw new InvalidArgumentException('Annotation key cannot be empty');
        }

        if (1 !== preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $tag)) {
            throw new InvalidArgumentException(sprintf('Invalid annotation key "%s". Must start with letter and contain only letters, numbers, underscore, or dash.', $tag));
        }
    }

    private static function normalizeString(string $tag, mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf('Value of annotation "%s" must be a string, a list of strings or null, %s given', $tag, get_debug_type($value)));
    }
}
