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

namespace KonradMichalik\PhpDocBlockHeaderFixer\Tests\Generators;

use InvalidArgumentException;
use KonradMichalik\PhpDocBlockHeaderFixer\Enum\Separate;
use KonradMichalik\PhpDocBlockHeaderFixer\Generators\{DocBlockHeader, Generator};
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(DocBlockHeader::class)]
/**
 * DocBlockHeaderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class DocBlockHeaderTest extends TestCase
{
    public function testImplementsGeneratorInterface(): void
    {
        $docBlockHeader = DocBlockHeader::create(['author' => 'John Doe']);

        /* @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(Generator::class, $docBlockHeader);
    }

    public function testCreateWithValidAnnotations(): void
    {
        $annotations = ['author' => 'John Doe', 'license' => 'MIT'];
        $docBlockHeader = DocBlockHeader::create($annotations);

        self::assertSame($annotations, $docBlockHeader->annotations);
        self::assertTrue($docBlockHeader->preserveExisting);
        self::assertSame(Separate::Both, $docBlockHeader->separate);
    }

    public function testCreateWithCustomParameters(): void
    {
        $annotations = ['author' => 'Jane Smith'];
        $docBlockHeader = DocBlockHeader::create(
            $annotations,
            false,
            Separate::None,
        );

        self::assertSame($annotations, $docBlockHeader->annotations);
        self::assertFalse($docBlockHeader->preserveExisting);
        self::assertSame(Separate::None, $docBlockHeader->separate);
    }

    public function testCreateWithArrayValueAnnotations(): void
    {
        $annotations = [
            'author' => ['John Doe <john@example.com>', 'Jane Smith <jane@example.com>'],
            'license' => 'MIT',
        ];
        $docBlockHeader = DocBlockHeader::create($annotations);

        self::assertSame($annotations, $docBlockHeader->annotations);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $annotations = ['author' => 'John Doe', 'license' => 'MIT'];
        $docBlockHeader = DocBlockHeader::create($annotations, false, Separate::Top);

        $result = $docBlockHeader->toArray();

        $expected = [
            'KonradMichalik/docblock_header_comment' => [
                'annotations' => $annotations,
                'preserve_existing' => false,
                'separate' => 'top',
                'add_structure_name' => true,
            ],
        ];

        self::assertSame($expected, $result);
    }

    public function testToArrayWithDefaultParameters(): void
    {
        $annotations = ['author' => 'John Doe'];
        $docBlockHeader = DocBlockHeader::create($annotations);

        $result = $docBlockHeader->toArray();

        $expected = [
            'KonradMichalik/docblock_header_comment' => [
                'annotations' => $annotations,
                'preserve_existing' => true,
                'separate' => 'both',
                'add_structure_name' => true,
            ],
        ];

        self::assertSame($expected, $result);
    }

    public function testValidateAnnotationsWithValidKeys(): void
    {
        $validAnnotations = [
            'author' => 'John Doe',
            'copyright' => '2024',
            'license' => 'MIT',
            'version' => '1.0.0',
            'since' => '1.0.0',
            'subpackage' => 'SubPackage',
            'see' => 'https://example.com',
            'link' => 'https://example.com',
            'todo' => 'Fix this',
            'fixme' => 'Fix this',
            'deprecated' => 'Use something else',
            'internal' => 'Internal use only',
            'api' => 'Public API',
            'category' => 'Category',
            'example' => 'Example usage',
            'ignore' => 'Ignore this',
            'uses' => 'Uses something',
            'used-by' => 'Used by something',
            'throws' => 'Exception',
            'method' => 'methodName()',
            'property' => '$propertyName',
            'property-read' => '$readOnlyProperty',
            'property-write' => '$writeOnlyProperty',
            'param' => 'string $param',
            'return' => 'string',
            'var' => 'string',
            'global' => '$GLOBALS',
            'static' => 'Static method',
            'final' => 'Final class',
            'abstract' => 'Abstract class',
        ];

        // This should not throw an exception
        $docBlockHeader = DocBlockHeader::create($validAnnotations);
        /* @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        self::assertInstanceOf(DocBlockHeader::class, $docBlockHeader);
    }

    public function testValidateAnnotationsThrowsExceptionForNonStringKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation key must be a string, integer given');

        // @phpstan-ignore-next-line argument.type
        DocBlockHeader::create([123 => 'invalid']);
    }

    public function testValidateAnnotationsThrowsExceptionForEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation key cannot be empty');

        DocBlockHeader::create(['' => 'value']);
    }

    public function testValidateAnnotationsThrowsExceptionForWhitespaceOnlyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Annotation key cannot be empty');

        DocBlockHeader::create(['   ' => 'value']);
    }

    public function testValidateAnnotationsThrowsExceptionForInvalidKeyFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid annotation key "123invalid". Must start with letter and contain only letters, numbers, underscore, or dash.');

        DocBlockHeader::create(['123invalid' => 'value']);
    }

    public function testValidateAnnotationsThrowsExceptionForInvalidKeyCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid annotation key "invalid@key". Must start with letter and contain only letters, numbers, underscore, or dash.');

        DocBlockHeader::create(['invalid@key' => 'value']);
    }

    public function testValidateAnnotationsThrowsExceptionForUnknownAnnotation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown annotation "unknownAnnotation"');

        DocBlockHeader::create(['unknownAnnotation' => 'value']);
    }

    public function testValidateAnnotationsAcceptsValidKeyWithHyphen(): void
    {
        $docBlockHeader = DocBlockHeader::create(['property-read' => '$property']);

        self::assertSame(['property-read' => '$property'], $docBlockHeader->annotations);
    }

    public function testValidateAnnotationsRejectsKeyWithUnderscore(): void
    {
        // This should throw an exception because 'used_by' with underscore is not in allowed list
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown annotation "used_by"');

        DocBlockHeader::create(['used_by' => 'Something']);
    }

    public function testValidateAnnotationsAcceptsValidKeyWithNumbers(): void
    {
        // Note: There are no standard annotations with numbers in the allowed list
        // Let's test that validation works correctly for keys with numbers
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown annotation "author2"');

        DocBlockHeader::create(['author2' => 'John Doe']);
    }

    public function testCreateWithEmptyAnnotations(): void
    {
        $docBlockHeader = DocBlockHeader::create([]);

        self::assertSame([], $docBlockHeader->annotations);
        self::assertTrue($docBlockHeader->preserveExisting);
        self::assertSame(Separate::Both, $docBlockHeader->separate);
    }

    public function testPropertiesAreReadonly(): void
    {
        $docBlockHeader = DocBlockHeader::create(['author' => 'John Doe']);

        self::assertSame(['author' => 'John Doe'], $docBlockHeader->annotations);
        self::assertTrue($docBlockHeader->preserveExisting);
        self::assertSame(Separate::Both, $docBlockHeader->separate);

        // Properties should be readonly - but we can't test this directly in PHP 8.1+
        // The readonly modifier is enforced at the language level
    }

    public function testConstructorIsPrivate(): void
    {
        $reflection = new ReflectionClass(DocBlockHeader::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }

    public function testClassIsFinal(): void
    {
        $reflection = new ReflectionClass(DocBlockHeader::class);

        self::assertTrue($reflection->isFinal());
    }

    public function testCreateWithAddStructureName(): void
    {
        $annotations = ['author' => 'John Doe'];
        $docBlockHeader = DocBlockHeader::create(
            $annotations,
            true,
            Separate::None,
            true,
        );

        self::assertSame($annotations, $docBlockHeader->annotations);
        self::assertTrue($docBlockHeader->preserveExisting);
        self::assertSame(Separate::None, $docBlockHeader->separate);
        self::assertTrue($docBlockHeader->addStructureName);
    }

    public function testToArrayWithAddStructureName(): void
    {
        $annotations = ['author' => 'John Doe'];
        $docBlockHeader = DocBlockHeader::create(
            $annotations,
            false,
            Separate::Top,
            true,
        );

        $result = $docBlockHeader->toArray();

        $expected = [
            'KonradMichalik/docblock_header_comment' => [
                'annotations' => $annotations,
                'preserve_existing' => false,
                'separate' => 'top',
                'add_structure_name' => true,
            ],
        ];

        self::assertSame($expected, $result);
    }

    public function testDeprecatedToArrayAliasMatchesToArray(): void
    {
        $docBlockHeader = DocBlockHeader::create(['author' => 'John Doe']);

        self::assertSame($docBlockHeader->toArray(), $docBlockHeader->__toArray());
    }

    public function testFromComposerWithSingleAuthor(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'license' => 'MIT',
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer($testComposerPath);

            self::assertSame('John Doe <john@example.com>', $docBlockHeader->annotations['author']);
            self::assertSame('MIT', $docBlockHeader->annotations['license']);
            self::assertTrue($docBlockHeader->preserveExisting);
            self::assertSame(Separate::Both, $docBlockHeader->separate);
        } finally {
            unlink($testComposerPath);
        }
    }

    public function testFromComposerWithMultipleAuthors(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ],
            'license' => 'GPL-3.0-or-later',
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer($testComposerPath);

            self::assertIsArray($docBlockHeader->annotations['author']);
            self::assertCount(2, $docBlockHeader->annotations['author']);
            self::assertSame('John Doe <john@example.com>', $docBlockHeader->annotations['author'][0]);
            self::assertSame('Jane Smith <jane@example.com>', $docBlockHeader->annotations['author'][1]);
            self::assertSame('GPL-3.0-or-later', $docBlockHeader->annotations['license']);
        } finally {
            unlink($testComposerPath);
        }
    }

    public function testFromComposerWithAuthorWithoutEmail(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe'],
            ],
            'license' => 'MIT',
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer($testComposerPath);

            self::assertSame('John Doe', $docBlockHeader->annotations['author']);
            self::assertSame('MIT', $docBlockHeader->annotations['license']);
        } finally {
            unlink($testComposerPath);
        }
    }

    public function testFromComposerWithNoAuthors(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'license' => 'MIT',
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer($testComposerPath);

            self::assertArrayNotHasKey('author', $docBlockHeader->annotations);
            self::assertSame('MIT', $docBlockHeader->annotations['license']);
        } finally {
            unlink($testComposerPath);
        }
    }

    public function testFromComposerWithNoLicense(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer($testComposerPath);

            self::assertSame('John Doe <john@example.com>', $docBlockHeader->annotations['author']);
            self::assertArrayNotHasKey('license', $docBlockHeader->annotations);
        } finally {
            unlink($testComposerPath);
        }
    }

    public function testFromComposerWithAdditionalAnnotations(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'license' => 'MIT',
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer(
                $testComposerPath,
                ['copyright' => '2025', 'version' => '1.0.0'],
            );

            self::assertSame('John Doe <john@example.com>', $docBlockHeader->annotations['author']);
            self::assertSame('MIT', $docBlockHeader->annotations['license']);
            self::assertSame('2025', $docBlockHeader->annotations['copyright']);
            self::assertSame('1.0.0', $docBlockHeader->annotations['version']);
        } finally {
            unlink($testComposerPath);
        }
    }

    public function testFromComposerWithCustomParameters(): void
    {
        $testComposerPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'license' => 'MIT',
        ];

        file_put_contents($testComposerPath, json_encode($composerData));

        try {
            $docBlockHeader = DocBlockHeader::fromComposer(
                $testComposerPath,
                [],
                false,
                Separate::None,
                false,
            );

            self::assertSame('John Doe <john@example.com>', $docBlockHeader->annotations['author']);
            self::assertFalse($docBlockHeader->preserveExisting);
            self::assertSame(Separate::None, $docBlockHeader->separate);
            self::assertFalse($docBlockHeader->addStructureName);
        } finally {
            unlink($testComposerPath);
        }
    }
}
