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

namespace KonradMichalik\PhpDocBlockHeaderFixer\Tests\Service;

use InvalidArgumentException;
use KonradMichalik\PhpDocBlockHeaderFixer\Service\AnnotationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(AnnotationService::class)]
/**
 * AnnotationServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class AnnotationServiceTest extends TestCase
{
    public function testNormalizeKeepsValidAnnotations(): void
    {
        $annotations = [
            'author' => ['John Doe', 'Jane Doe'],
            'license' => 'MIT',
            'internal' => null,
            'property-read' => 'string $name',
        ];

        self::assertSame($annotations, AnnotationService::normalize($annotations));
    }

    public function testNormalizeCastsScalarValuesToString(): void
    {
        self::assertSame(
            ['since' => '2024', 'version' => '1.5', 'see' => ['1', 'Foo']],
            AnnotationService::normalize(['since' => 2024, 'version' => 1.5, 'see' => [1, 'Foo']]),
        );
    }

    public function testNormalizeReindexesListWithGaps(): void
    {
        self::assertSame(['author' => ['A', 'B']], AnnotationService::normalize(['author' => [0 => 'A', 2 => 'B']]));
    }

    public function testNormalizeKeepsStrategyFormAndFillsDefaultStrategy(): void
    {
        self::assertSame(
            [
                'author' => ['value' => 'John Doe', 'strategy' => 'append'],
                'license' => ['value' => 'MIT', 'strategy' => 'enforce'],
                'since' => ['value' => ['2024'], 'strategy' => 'enforce'],
            ],
            AnnotationService::normalize([
                'author' => ['value' => 'John Doe', 'strategy' => 'append'],
                'license' => ['value' => 'MIT'],
                'since' => ['value' => [2024], 'strategy' => 'enforce'],
            ]),
        );
    }

    /**
     * @param array<mixed> $annotations
     */
    #[DataProvider('invalidAnnotationsProvider')]
    public function testNormalizeRejectsInvalidAnnotations(array $annotations, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        AnnotationService::normalize($annotations);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidAnnotationsProvider(): iterable
    {
        yield 'non-string key' => [[123 => 'x'], 'Annotation key must be a string, integer given'];
        yield 'empty key' => [['' => 'x'], 'Annotation key cannot be empty'];
        yield 'whitespace key' => [['   ' => 'x'], 'Annotation key cannot be empty'];
        yield 'key with space' => [['foo bar' => 'x'], 'Invalid annotation key "foo bar".'];
        yield 'key starting with digit' => [['1foo' => 'x'], 'Invalid annotation key "1foo".'];
        yield 'bool value' => [['since' => true], 'Value of annotation "since" must be a string, a list of strings or null, bool given'];
        yield 'object value' => [['since' => new stdClass()], 'Value of annotation "since" must be a string, a list of strings or null, stdClass given'];
        yield 'nested array value' => [['author' => [['x']]], 'Value of annotation "author" must be a string, a list of strings or null, array given'];
        yield 'null in list' => [['author' => [null]], 'Value of annotation "author" must be a string, a list of strings or null, null given'];
        yield 'unknown strategy' => [['author' => ['value' => 'x', 'strategy' => 'merge']], 'Strategy of annotation "author" must be "enforce" or "append", "merge" given'];
        yield 'missing value' => [['author' => ['strategy' => 'append']], 'Annotation "author" must define a "value" next to its "strategy"'];
        yield 'unknown option key' => [['author' => ['value' => 'x', 'mode' => 'append']], 'Annotation "author" only accepts the keys "value" and "strategy", "mode" given'];
    }
}
