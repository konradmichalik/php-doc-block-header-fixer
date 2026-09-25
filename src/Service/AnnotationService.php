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

use function array_key_exists;
use function get_debug_type;
use function gettype;
use function in_array;
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
    public const STRATEGY_ENFORCE = 'enforce';

    public const STRATEGY_APPEND = 'append';

    /**
     * Validates annotation tags and values and casts numeric values to strings.
     *
     * A value is a string, a list of strings, null, or an array with a "value" in
     * one of those forms and a "strategy" ("enforce" by default, or "append").
     *
     * @param array<mixed> $annotations
     *
     * @return array<string, string|list<string>|array{value: string|list<string>|null, strategy: string}|null>
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(array $annotations): array
    {
        $normalized = [];

        foreach ($annotations as $tag => $value) {
            self::validateTag($tag);

            // Recognised by its keys only, so a list with gaps (array_unique(), array_filter()) stays a list
            $isStrategyForm = is_array($value) && (array_key_exists('value', $value) || array_key_exists('strategy', $value));

            $normalized[$tag] = $isStrategyForm
                ? self::normalizeStrategyForm($tag, $value)
                : self::normalizeValue($tag, $value);
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $option
     *
     * @return array{value: string|list<string>|null, strategy: string}
     */
    private static function normalizeStrategyForm(string $tag, array $option): array
    {
        foreach (array_keys($option) as $key) {
            if (!in_array($key, ['value', 'strategy'], true)) {
                throw new InvalidArgumentException(sprintf('Annotation "%s" only accepts the keys "value" and "strategy", "%s" given', $tag, $key));
            }
        }

        if (!array_key_exists('value', $option)) {
            throw new InvalidArgumentException(sprintf('Annotation "%s" must define a "value" next to its "strategy"', $tag));
        }

        $strategy = $option['strategy'] ?? self::STRATEGY_ENFORCE;
        if (!in_array($strategy, [self::STRATEGY_ENFORCE, self::STRATEGY_APPEND], true)) {
            throw new InvalidArgumentException(sprintf('Strategy of annotation "%s" must be "enforce" or "append", %s given', $tag, is_string($strategy) ? '"'.$strategy.'"' : get_debug_type($strategy)));
        }

        $value = $option['value'];
        if (is_array($value) && !array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Value of annotation "%s" must be a string, a list of strings or null, array given', $tag));
        }

        return ['value' => self::normalizeValue($tag, $value), 'strategy' => $strategy];
    }

    /**
     * @return string|list<string>|null
     */
    private static function normalizeValue(string $tag, mixed $value): string|array|null
    {
        if (null === $value) {
            return null;
        }

        return is_array($value)
            ? array_values(array_map(static fn (mixed $singleValue): string => self::normalizeString($tag, $singleValue), $value))
            : self::normalizeString($tag, $value);
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
