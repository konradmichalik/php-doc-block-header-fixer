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

use KonradMichalik\PhpDocBlockHeaderFixer\Service\ComposerService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ComposerService::class)]
/**
 * ComposerServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class ComposerServiceTest extends TestCase
{
    private string $testComposerJsonPath;

    protected function setUp(): void
    {
        $this->testComposerJsonPath = sys_get_temp_dir().'/test-composer-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testComposerJsonPath)) {
            unlink($this->testComposerJsonPath);
        }
    }

    public function testReadComposerJsonSuccess(): void
    {
        $composerData = [
            'name' => 'test/package',
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
            ],
            'license' => 'MIT',
        ];

        file_put_contents($this->testComposerJsonPath, json_encode($composerData));

        $result = ComposerService::readComposerJson($this->testComposerJsonPath);

        self::assertSame($composerData, $result);
    }

    public function testReadComposerJsonThrowsExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The file "non-existent.json" does not exist.');

        ComposerService::readComposerJson('non-existent.json');
    }

    public function testReadComposerJsonThrowsExceptionWhenFileCannotBeRead(): void
    {
        // Create an empty directory to simulate unreadable file
        $dirPath = sys_get_temp_dir().'/test-dir-'.uniqid();
        mkdir($dirPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unable to (read file|decode JSON)/');

        try {
            // Suppress the expected PHP notice when reading a directory
            @ComposerService::readComposerJson($dirPath);
        } finally {
            rmdir($dirPath);
        }
    }

    public function testReadComposerJsonThrowsExceptionOnReadFailure(): void
    {
        // Create a file with no read permissions to force file_get_contents to return false
        $invalidPath = sys_get_temp_dir().'/test-unreadable-'.uniqid().'.json';
        file_put_contents($invalidPath, '{}');

        // Make file unreadable (this may not work on all systems, especially Windows)
        chmod($invalidPath, 0000);

        try {
            // Only run this test if we can actually make the file unreadable
            if (!is_readable($invalidPath)) {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Unable to read file');
                // Suppress the expected PHP warning when reading an unreadable file
                @ComposerService::readComposerJson($invalidPath);
            } else {
                // If we can't make the file unreadable, mark test as skipped
                $this->markTestSkipped('Unable to create unreadable file on this platform');
            }
        } finally {
            // Restore permissions before deletion
            @chmod($invalidPath, 0644);
            @unlink($invalidPath);
        }
    }

    public function testReadComposerJsonThrowsExceptionWhenJsonIsInvalid(): void
    {
        file_put_contents($this->testComposerJsonPath, 'invalid json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to decode JSON');

        ComposerService::readComposerJson($this->testComposerJsonPath);
    }

    public function testExtractLicenseWithStringLicense(): void
    {
        $composerData = ['license' => 'MIT'];

        $result = ComposerService::extractLicense($composerData);

        self::assertSame('MIT', $result);
    }

    public function testExtractLicenseWithArrayLicense(): void
    {
        $composerData = ['license' => ['MIT', 'Apache-2.0']];

        $result = ComposerService::extractLicense($composerData);

        self::assertSame('(MIT or Apache-2.0)', $result);
    }

    public function testExtractLicenseWithSingleEntryArray(): void
    {
        $composerData = ['license' => ['MIT']];

        $result = ComposerService::extractLicense($composerData);

        self::assertSame('MIT', $result);
    }

    public function testExtractLicenseWithEmptyArray(): void
    {
        $composerData = ['license' => []];

        $result = ComposerService::extractLicense($composerData);

        self::assertNull($result);
    }

    public function testExtractLicenseWhenNotSet(): void
    {
        $composerData = ['name' => 'test/package'];

        $result = ComposerService::extractLicense($composerData);

        self::assertNull($result);
    }

    public function testExtractAuthorsWithValidAuthors(): void
    {
        $composerData = [
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'Developer'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ],
        ];

        $result = ComposerService::extractAuthors($composerData);

        self::assertCount(2, $result);
        self::assertSame('John Doe', $result[0]['name']);
        /* @phpstan-ignore-next-line offsetAccess.notFound */
        self::assertSame('john@example.com', $result[0]['email']);
        /* @phpstan-ignore-next-line offsetAccess.notFound */
        self::assertSame('Developer', $result[0]['role']);
        self::assertSame('Jane Smith', $result[1]['name']);
        /* @phpstan-ignore-next-line offsetAccess.notFound */
        self::assertSame('jane@example.com', $result[1]['email']);
    }

    public function testExtractAuthorsWithNameOnly(): void
    {
        $composerData = [
            'authors' => [
                ['name' => 'John Doe'],
            ],
        ];

        $result = ComposerService::extractAuthors($composerData);

        self::assertCount(1, $result);
        self::assertSame('John Doe', $result[0]['name']);
        self::assertArrayNotHasKey('email', $result[0]);
    }

    public function testExtractAuthorsFiltersOutInvalidEntries(): void
    {
        $composerData = [
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['email' => 'no-name@example.com'], // Missing name
                'invalid', // Not an array
                ['name' => 'Jane Smith'],
            ],
        ];

        $result = ComposerService::extractAuthors($composerData);

        self::assertCount(2, $result);
        self::assertSame('John Doe', $result[0]['name']);
        self::assertSame('Jane Smith', $result[1]['name']);
    }

    public function testExtractAuthorsWhenNotSet(): void
    {
        $composerData = ['name' => 'test/package'];

        $result = ComposerService::extractAuthors($composerData);

        self::assertSame([], $result);
    }

    public function testExtractAuthorsWhenNotArray(): void
    {
        $composerData = ['authors' => 'not an array'];

        $result = ComposerService::extractAuthors($composerData);

        self::assertSame([], $result);
    }

    public function testGetPrimaryAuthor(): void
    {
        $composerData = [
            'authors' => [
                ['name' => 'John Doe', 'email' => 'john@example.com'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ],
        ];

        $result = ComposerService::getPrimaryAuthor($composerData);

        self::assertNotNull($result);
        self::assertSame('John Doe', $result['name']);
        /* @phpstan-ignore-next-line offsetAccess.notFound */
        self::assertSame('john@example.com', $result['email']);
    }

    public function testGetPrimaryAuthorWhenNoAuthors(): void
    {
        $composerData = ['name' => 'test/package'];

        $result = ComposerService::getPrimaryAuthor($composerData);

        self::assertNull($result);
    }
}
