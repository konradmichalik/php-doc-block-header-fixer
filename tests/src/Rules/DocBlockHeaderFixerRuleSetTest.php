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

namespace KonradMichalik\PhpDocBlockHeaderFixer\Tests\Rules;

use KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer;
use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet;
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(DocBlockHeaderFixer::class)]
/**
 * DocBlockHeaderFixerRuleSetTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class DocBlockHeaderFixerRuleSetTest extends TestCase
{
    private const ANNOTATIONS = ['author' => 'Konrad <k@x.de>', 'license' => 'MIT'];

    private const DOCBLOCK = "/**\n * @author Konrad <k@x.de>\n * @license MIT\n */";

    /**
     * @return iterable<string, array{string}>
     */
    public static function separateProvider(): iterable
    {
        yield 'none' => ['none'];
        yield 'top' => ['top'];
        yield 'bottom' => ['bottom'];
        yield 'both' => ['both'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function codeProvider(): iterable
    {
        yield 'after namespace' => ["<?php\n\nnamespace App;\n\nfinal class A\n{\n}\n"];
        yield 'after opening tag' => ["<?php\nfinal class A\n{\n}\n"];
        yield 'after use statement' => ["<?php\n\nnamespace App;\n\nuse Foo\\Bar;\n#[Bar]\nfinal class A\n{\n}\n"];
        yield 'single-line docblock' => ["<?php\n\n/** @internal */ final class A\n{\n}\n"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function separateAndCodeProvider(): iterable
    {
        foreach (self::separateProvider() as $separateName => [$separate]) {
            foreach (self::codeProvider() as $codeName => [$code]) {
                yield "{$separateName}, {$codeName}" => [$separate, $code];
            }
        }
    }

    #[DataProvider('separateAndCodeProvider')]
    public function testConvergesWithSymfonyRuleSetInOneRun(string $separate, string $code): void
    {
        $config = ['annotations' => self::ANNOTATIONS, 'separate' => $separate];

        $firstRun = $this->fix($code, ['@Symfony' => true], $config);

        self::assertSame($firstRun, $this->fix($firstRun, ['@Symfony' => true], $config));
    }

    #[DataProvider('separateAndCodeProvider')]
    public function testLeavesNoAdjacentWhitespaceTokens(string $separate, string $code): void
    {
        $tokens = Tokens::fromCode($code);
        $fixer = new DocBlockHeaderFixer();
        $fixer->configure(['annotations' => self::ANNOTATIONS, 'separate' => $separate]);
        $fixer->fix(new SplFileInfo(__FILE__), $tokens);

        for ($index = 1, $count = $tokens->count(); $index < $count; ++$index) {
            self::assertFalse(
                $tokens[$index - 1]->isWhitespace() && $tokens[$index]->isWhitespace(),
                "Adjacent whitespace tokens at index {$index}",
            );
        }
    }

    public function testSeparateTopEnsuresExactlyOneBlankLineBeforeDocBlock(): void
    {
        $code = "<?php\n\nnamespace App;\n\nfinal class A {}\n";

        self::assertSame(
            "<?php\n\nnamespace App;\n\n".self::DOCBLOCK."\nfinal class A {}\n",
            $this->fix($code, [], ['annotations' => self::ANNOTATIONS, 'separate' => 'top']),
        );
    }

    public function testSeparateTopAddsBlankLineAfterOpeningTag(): void
    {
        $code = "<?php\nfinal class A {}\n";

        self::assertSame(
            "<?php\n\n".self::DOCBLOCK."\nfinal class A {}\n",
            $this->fix($code, [], ['annotations' => self::ANNOTATIONS, 'separate' => 'top']),
        );
    }

    public function testSeparateBottomAddsBlankLineBetweenDocBlockAndStructure(): void
    {
        $code = "<?php\n\nfinal class A {}\n";

        self::assertSame(
            "<?php\n\n".self::DOCBLOCK."\n\nfinal class A {}\n",
            $this->fix($code, [], ['annotations' => self::ANNOTATIONS, 'separate' => 'bottom']),
        );
    }

    public function testNewDocBlockIsFollowedByNewlineWithoutEnsureSpacing(): void
    {
        $code = "<?php\n\nfinal class A {}\n";

        self::assertSame(
            "<?php\n\n".self::DOCBLOCK."\nfinal class A {}\n",
            $this->fix($code, [], ['annotations' => self::ANNOTATIONS, 'ensure_spacing' => false]),
        );
    }

    public function testMergedSingleLineDocBlockKeepsNoLeadingSpaceBeforeStructure(): void
    {
        $code = "<?php\n\n/** @internal */ final class A {}\n";

        self::assertSame(
            "<?php\n\n/**\n * @internal\n * @author Konrad <k@x.de>\n * @license MIT\n */\nfinal class A {}\n",
            $this->fix($code, [], ['annotations' => self::ANNOTATIONS]),
        );
    }

    public function testNewDocBlockKeepsIndentationOfStructure(): void
    {
        $code = "<?php\n\nnamespace App {\n    final class A {}\n}\n";

        $result = $this->fix($code, [], ['annotations' => self::ANNOTATIONS, 'separate' => 'both']);

        self::assertStringContainsString("\n\n    /**", $result);
        self::assertStringContainsString("*/\n\n    final class A {}", $result);
    }

    public function testMergedSingleLineDocBlockKeepsIndentationOfStructure(): void
    {
        $code = "<?php\n\nnamespace App {\n    /** @internal */ final class A {}\n}\n";

        $result = $this->fix($code, [], ['annotations' => self::ANNOTATIONS]);

        self::assertStringContainsString("*/\n    final class A {}", $result);
    }

    /**
     * Runs the fixer together with a rule set the way the php-cs-fixer runner does,
     * starting from a fresh tokenization.
     *
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $config
     */
    private function fix(string $code, array $rules, array $config): string
    {
        $factory = (new FixerFactory())
            ->registerBuiltInFixers()
            ->registerCustomFixers([new DocBlockHeaderFixer()])
            ->useRuleSet(new RuleSet([...$rules, 'KonradMichalik/docblock_header_comment' => $config]));

        Tokens::clearCache();
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        foreach ($factory->getFixers() as $fixer) {
            $fixer->fix($file, $tokens);

            if ($tokens->isChanged()) {
                $tokens->clearEmptyTokens();
                $tokens->clearChanged();
            }
        }

        return $tokens->generateCode();
    }
}
