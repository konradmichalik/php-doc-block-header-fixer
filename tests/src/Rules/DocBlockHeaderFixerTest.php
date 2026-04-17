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
use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileInfo;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(DocBlockHeaderFixer::class)]
/**
 * DocBlockHeaderFixerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
final class DocBlockHeaderFixerTest extends TestCase
{
    private DocBlockHeaderFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new DocBlockHeaderFixer();
    }

    public function testGetDefinition(): void
    {
        $definition = $this->fixer->getDefinition();

        self::assertSame('Add configurable DocBlock annotations before class, interface, trait, and enum declarations.', $definition->getSummary());
    }

    public function testGetName(): void
    {
        self::assertSame('KonradMichalik/docblock_header_comment', $this->fixer->getName());
    }

    public function testGetPriority(): void
    {
        self::assertSame(1, $this->fixer->getPriority());
    }

    public function testIsCandidate(): void
    {
        $tokens = Tokens::fromCode('<?php class Foo {}');

        self::assertTrue($this->fixer->isCandidate($tokens));
    }

    public function testIsCandidateReturnsFalseWhenNoClass(): void
    {
        $tokens = Tokens::fromCode('<?php function foo() {}');

        self::assertFalse($this->fixer->isCandidate($tokens));
    }

    public function testBuildDocBlockWithEmptyAnnotations(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, [], 'TestClass');

        self::assertSame("/**\n */", $result);
    }

    public function testBuildDocBlockWithSingleAnnotation(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, ['author' => 'John Doe <john@example.com>'], '');

        $expected = "/**\n * @author John Doe <john@example.com>\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithMultipleAnnotations(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, [
            'author' => 'John Doe <john@example.com>',
            'license' => 'MIT',
        ], '');

        $expected = "/**\n * @author John Doe <john@example.com>\n * @license MIT\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithArrayValue(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, [
            'author' => [
                'John Doe <john@example.com>',
                'Jane Smith <jane@example.com>',
            ],
            'license' => 'MIT',
        ], '');

        $expected = "/**\n * @author John Doe <john@example.com>\n * @author Jane Smith <jane@example.com>\n * @license MIT\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithEmptyValue(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, [
            'deprecated' => '',
            'author' => 'John Doe',
        ], '');

        $expected = "/**\n * @deprecated\n * @author John Doe\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithNullValue(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, [
            'internal' => null,
            'license' => 'MIT',
        ], '');

        $expected = "/**\n * @internal\n * @license MIT\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithMixedEmptyAndNonEmptyValues(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $result = $method->invoke($this->fixer, [
            'deprecated' => '',
            'author' => 'John Doe',
            'internal' => null,
            'license' => 'MIT',
            'api' => '',
        ], '');

        $expected = "/**\n * @deprecated\n * @author John Doe\n * @internal\n * @license MIT\n * @api\n */";
        self::assertSame($expected, $result);
    }

    public function testSupportsMethod(): void
    {
        $file = new SplFileInfo(__FILE__);

        self::assertTrue($this->fixer->supports($file));
    }

    public function testConfigurationDefinition(): void
    {
        $configDefinition = $this->fixer->getConfigurationDefinition();
        $options = $configDefinition->getOptions();

        self::assertCount(5, $options);

        $optionNames = array_map(static fn ($option) => $option->getName(), $options);
        self::assertContains('annotations', $optionNames);
        self::assertContains('preserve_existing', $optionNames);
        self::assertContains('separate', $optionNames);
        self::assertContains('add_structure_name', $optionNames);
        self::assertContains('ensure_spacing', $optionNames);
    }

    public function testParseExistingAnnotations(): void
    {
        $method = new ReflectionMethod($this->fixer, 'parseExistingAnnotations');

        $docBlock = "/**\n * @author John Doe\n * @license MIT\n */";
        $result = $method->invoke($this->fixer, $docBlock);

        $expected = [
            'author' => 'John Doe',
            'license' => 'MIT',
        ];

        self::assertSame($expected, $result);
    }

    public function testParseExistingAnnotationsWithDuplicates(): void
    {
        $method = new ReflectionMethod($this->fixer, 'parseExistingAnnotations');

        $docBlock = "/**\n * @implements ArrayAccess<int, string>\n * @implements IteratorAggregate<int, string>\n */";
        $result = $method->invoke($this->fixer, $docBlock);

        self::assertIsArray($result);
        self::assertArrayHasKey('implements', $result);
        self::assertIsArray($result['implements']);
        self::assertCount(2, $result['implements']);
        self::assertSame('ArrayAccess<int, string>', $result['implements'][0]);
        self::assertSame('IteratorAggregate<int, string>', $result['implements'][1]);
    }

    public function testParseExistingAnnotationsWithMultipleDuplicateTags(): void
    {
        $method = new ReflectionMethod($this->fixer, 'parseExistingAnnotations');

        $docBlock = "/**\n * @author First Author\n * @license MIT\n * @author Second Author\n * @author Third Author\n */";
        $result = $method->invoke($this->fixer, $docBlock);

        self::assertIsArray($result);
        self::assertArrayHasKey('author', $result);
        self::assertIsArray($result['author']);
        self::assertCount(3, $result['author']);
        self::assertSame('First Author', $result['author'][0]);
        self::assertSame('Second Author', $result['author'][1]);
        self::assertSame('Third Author', $result['author'][2]);
        self::assertSame('MIT', $result['license']);
    }

    public function testParseExistingAnnotationsWithDuplicateEmptyValues(): void
    {
        $method = new ReflectionMethod($this->fixer, 'parseExistingAnnotations');

        $docBlock = "/**\n * @internal\n * @api\n * @internal\n */";
        $result = $method->invoke($this->fixer, $docBlock);

        self::assertIsArray($result);
        self::assertArrayHasKey('internal', $result);
        self::assertIsArray($result['internal']);
        self::assertCount(2, $result['internal']);
        self::assertSame('', $result['internal'][0]);
        self::assertSame('', $result['internal'][1]);
        self::assertSame('', $result['api']);
    }

    public function testMergeAnnotations(): void
    {
        $method = new ReflectionMethod($this->fixer, 'mergeAnnotations');

        $existing = ['author' => 'Existing Author', 'version' => '1.0'];
        $new = ['author' => 'New Author', 'license' => 'MIT'];

        $result = $method->invoke($this->fixer, $existing, $new);

        $expected = [
            'author' => 'New Author',
            'version' => '1.0',
            'license' => 'MIT',
        ];

        self::assertSame($expected, $result);
    }

    public function testApplyFixWithEmptyAnnotations(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure(['annotations' => []]);
        $method->invoke($this->fixer, $file, $tokens);

        self::assertSame($code, $tokens->generateCode());
    }

    public function testApplyFixAddsDocBlockToClass(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $expected = "<?php /**\n * @author John Doe\n */class Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testApplyFixHandlesMultipleClasses(): void
    {
        $code = '<?php class Foo {} class Bar {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $expected = "<?php /**\n * @author John Doe\n */class Foo {} /**\n * @author John Doe\n */class Bar {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testProcessClassDocBlockWithNewDocBlock(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'processStructureDocBlock');

        $this->fixer->configure(['separate' => 'none', 'ensure_spacing' => false]);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        $expected = "<?php /**\n * @author John Doe\n */class Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testProcessClassDocBlockWithExistingDocBlockPreserve(): void
    {
        $code = "<?php /**\n * @license MIT\n */ class Foo {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'processStructureDocBlock');

        $this->fixer->configure(['preserve_existing' => true]);
        $method->invoke($this->fixer, $tokens, 2, $annotations, 'Foo');

        self::assertStringContainsString('@license MIT', $tokens->generateCode());
        self::assertStringContainsString('@author John Doe', $tokens->generateCode());
    }

    public function testProcessClassDocBlockWithExistingDocBlockReplace(): void
    {
        $code = "<?php /**\n * @license MIT\n */ class Foo {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'processStructureDocBlock');

        $this->fixer->configure(['preserve_existing' => false]);
        $method->invoke($this->fixer, $tokens, 2, $annotations, 'Foo');

        self::assertStringNotContainsString('@license MIT', $tokens->generateCode());
        self::assertStringContainsString('@author John Doe', $tokens->generateCode());
    }

    public function testFindExistingDocBlockFound(): void
    {
        $code = "<?php /**\n * @author John Doe\n */ class Foo {}";
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findExistingDocBlock');

        $result = $method->invoke($this->fixer, $tokens, 2);

        self::assertSame(1, $result);
    }

    public function testFindExistingDocBlockNotFound(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findExistingDocBlock');

        $result = $method->invoke($this->fixer, $tokens, 1);

        self::assertNull($result);
    }

    public function testFindExistingDocBlockWithModifiers(): void
    {
        $code = "<?php /**\n * @author John Doe\n */ final class Foo {}";
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findExistingDocBlock');

        $result = $method->invoke($this->fixer, $tokens, 3);

        self::assertSame(1, $result);
    }

    public function testMergeWithExistingDocBlock(): void
    {
        $code = "<?php /**\n * @license MIT\n */ class Foo {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'mergeWithExistingDocBlock');

        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        self::assertStringContainsString('@license MIT', $tokens->generateCode());
        self::assertStringContainsString('@author John Doe', $tokens->generateCode());
    }

    public function testReplaceDocBlock(): void
    {
        $code = "<?php /**\n * @license MIT\n */ class Foo {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'replaceDocBlock');

        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        self::assertStringNotContainsString('@license MIT', $tokens->generateCode());
        self::assertStringContainsString('@author John Doe', $tokens->generateCode());
    }

    public function testInsertNewDocBlockWithSeparateNone(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'insertNewDocBlock');

        $this->fixer->configure(['separate' => 'none', 'ensure_spacing' => false]);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        $expected = "<?php /**\n * @author John Doe\n */class Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testInsertNewDocBlockWithSeparateTop(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'insertNewDocBlock');

        $this->fixer->configure(['separate' => 'top']);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        $result = $tokens->generateCode();
        self::assertStringContainsString('@author John Doe', $result);
        self::assertGreaterThanOrEqual(3, substr_count($result, "\n"));
    }

    public function testInsertNewDocBlockWithSeparateBottom(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'insertNewDocBlock');

        $this->fixer->configure(['separate' => 'bottom']);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        $result = $tokens->generateCode();
        self::assertStringContainsString('@author John Doe', $result);
        self::assertGreaterThanOrEqual(3, substr_count($result, "\n"));
    }

    public function testInsertNewDocBlockWithSeparateBoth(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'insertNewDocBlock');

        $this->fixer->configure(['separate' => 'both']);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'Foo');

        $result = $tokens->generateCode();
        self::assertStringContainsString('@author John Doe', $result);
        self::assertGreaterThanOrEqual(4, substr_count($result, "\n"));
    }

    public function testFindInsertPositionSimpleClass(): void
    {
        $code = '<?php class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        $result = $method->invoke($this->fixer, $tokens, 1);

        self::assertSame(1, $result);
    }

    public function testFindInsertPositionWithFinalModifier(): void
    {
        $code = '<?php final class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        $result = $method->invoke($this->fixer, $tokens, 2);

        self::assertSame(1, $result);
    }

    public function testFindInsertPositionWithAbstractModifier(): void
    {
        $code = '<?php abstract class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        $result = $method->invoke($this->fixer, $tokens, 2);

        self::assertSame(1, $result);
    }

    public function testFindInsertPositionWithAttribute(): void
    {
        $code = '<?php #[SomeAttribute] class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        $result = $method->invoke($this->fixer, $tokens, 2);

        self::assertSame(1, $result);
    }

    public function testFindInsertPositionWithAttributeWithParameters(): void
    {
        $code = "<?php #[AsEventListener(identifier: 'my-identifier')] class Foo {}";
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        // Should return index 1 which is the #[ token
        self::assertSame(1, $result);
    }

    public function testApplyFixAddsDocBlockBeforeAttribute(): void
    {
        $code = "<?php\n#[AsEventListener(identifier: 'my-identifier')]\nclass Foo {}";
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $result = $tokens->generateCode();

        // DocBlock should be BEFORE the attribute, not between attribute and class
        self::assertMatchesRegularExpression('/@author John Doe.*#\[AsEventListener/s', $result);
    }

    public function testFindInsertPositionWithMultipleAttributes(): void
    {
        $code = "<?php\n#[Attribute1]\n#[Attribute2(param: 'value')]\nclass Foo {}";
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        // Should return the index of the first attribute (#[Attribute1])
        // Token structure: [0]=T_OPEN_TAG, [1]=T_WHITESPACE, [2]=T_ATTRIBUTE(#[), ...
        // The first #[Attribute1] token is at index 2
        self::assertTrue($tokens[$result]->isGivenKind(\T_ATTRIBUTE));
    }

    public function testFindExistingDocBlockWithAttributesBetween(): void
    {
        $code = "<?php\n/**\n * @author John Doe\n */\n#[SomeAttribute(param: 'value')]\nclass Foo {}";
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findExistingDocBlock');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        // Should find the DocBlock even with attribute in between
        self::assertNotNull($result);
        self::assertStringContainsString('@author John Doe', $tokens[$result]->getContent());
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.2')]
    public function testFindInsertPositionWithReadonlyModifier(): void
    {
        $code = '<?php readonly class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        // Should find the readonly modifier at index 1
        self::assertSame(1, $result);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.2')]
    public function testFindInsertPositionWithFinalReadonlyModifiers(): void
    {
        $code = '<?php final readonly class Foo {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'findInsertPosition');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        // Should find the final modifier at index 1 (the first modifier)
        self::assertSame(1, $result);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.2')]
    public function testApplyFixAddsDocBlockToFinalReadonlyClass(): void
    {
        $code = '<?php final readonly class Foo {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        // DocBlock should be placed BEFORE final, not between readonly and class
        $expected = "<?php /**\n * @author John Doe\n */final readonly class Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testBuildDocBlockWithClassName(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $this->fixer->configure(['add_structure_name' => true]);
        $result = $method->invoke($this->fixer, ['author' => 'John Doe'], 'MyClass');

        $expected = "/**\n * MyClass.\n *\n * @author John Doe\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithClassNameOnly(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $this->fixer->configure(['add_structure_name' => true]);
        $result = $method->invoke($this->fixer, [], 'MyClass');

        $expected = "/**\n * MyClass.\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithClassNameDisabled(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $this->fixer->configure(['add_structure_name' => false]);
        $result = $method->invoke($this->fixer, ['author' => 'John Doe'], 'MyClass');

        $expected = "/**\n * @author John Doe\n */";
        self::assertSame($expected, $result);
    }

    public function testBuildDocBlockWithEmptyClassName(): void
    {
        $method = new ReflectionMethod($this->fixer, 'buildDocBlock');

        $this->fixer->configure(['add_structure_name' => true]);
        $result = $method->invoke($this->fixer, ['author' => 'John Doe'], '');

        $expected = "/**\n * @author John Doe\n */";
        self::assertSame($expected, $result);
    }

    public function testGetClassName(): void
    {
        $code = '<?php class MyTestClass {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        $result = $method->invoke($this->fixer, $tokens, 1);

        self::assertSame('MyTestClass', $result);
    }

    public function testGetClassNameWithModifiers(): void
    {
        $code = '<?php final class FinalClass {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        self::assertSame('FinalClass', $result);
    }

    public function testGetClassNameHitsBreakOnNonStringToken(): void
    {
        // Use a valid class with annotations to find a non-T_STRING token after class
        $code = '<?php class MyClass { const TEST = 1; }';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        // Find the opening brace token (it comes after class name)
        $braceIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ('{' === $tokens[$i]->getContent()) {
                $braceIndex = $i - 1; // Use position just before brace
                break;
            }
        }

        // Call from a position where the next non-whitespace token is '{', not T_STRING
        // This should trigger the break on line 150
        $result = $method->invoke($this->fixer, $tokens, $braceIndex);

        // The method should find no T_STRING after this position and break, returning empty
        self::assertSame('', $result);
    }

    public function testGetStructureNameInterface(): void
    {
        $code = '<?php interface MyInterface {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        // Find the interface token index
        $interfaceIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_INTERFACE)) {
                $interfaceIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $interfaceIndex);

        self::assertSame('MyInterface', $result);
    }

    public function testGetStructureNameTrait(): void
    {
        $code = '<?php trait MyTrait {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        // Find the trait token index
        $traitIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_TRAIT)) {
                $traitIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $traitIndex);

        self::assertSame('MyTrait', $result);
    }

    public function testGetStructureNameEnum(): void
    {
        $code = '<?php enum MyEnum {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        // Find the enum token index
        $enumIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_ENUM)) {
                $enumIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $enumIndex);

        self::assertSame('MyEnum', $result);
    }

    public function testGetStructureNameReturnsEmptyWhenLoopCompletes(): void
    {
        $code = '<?php class MyClass {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'getStructureName');

        // Find the closing brace token - when we call getStructureName from there,
        // the loop should complete without finding anything and return empty string (line 153)
        $braceIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ('}' === $tokens[$i]->getContent()) {
                $braceIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $braceIndex);

        // Should return empty string because we're at the end and loop completes
        self::assertSame('', $result);
    }

    public function testIsCandidateInterface(): void
    {
        $tokens = Tokens::fromCode('<?php interface Foo {}');

        self::assertTrue($this->fixer->isCandidate($tokens));
    }

    public function testIsCandidateTrait(): void
    {
        $tokens = Tokens::fromCode('<?php trait Foo {}');

        self::assertTrue($this->fixer->isCandidate($tokens));
    }

    public function testIsCandidateEnum(): void
    {
        $tokens = Tokens::fromCode('<?php enum Foo {}');

        self::assertTrue($this->fixer->isCandidate($tokens));
    }

    public function testApplyFixAddsDocBlockToInterface(): void
    {
        $code = '<?php interface Foo {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $expected = "<?php /**\n * @author John Doe\n */interface Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testApplyFixAddsDocBlockToTrait(): void
    {
        $code = '<?php trait Foo {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'Jane Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $expected = "<?php /**\n * @author Jane Doe\n */trait Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testApplyFixAddsDocBlockToEnum(): void
    {
        $code = '<?php enum Foo {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['license' => 'MIT'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $expected = "<?php /**\n * @license MIT\n */enum Foo {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testApplyFixWithClassNameEnabled(): void
    {
        $code = '<?php class TestClass {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'add_structure_name' => true,
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $expected = "<?php /**\n * TestClass.\n *\n * @author John Doe\n */class TestClass {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testApplyFixWithMultipleClassesAndClassName(): void
    {
        $code = '<?php class FirstClass {} class SecondClass {}';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'add_structure_name' => true,
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $result = $tokens->generateCode();
        self::assertStringContainsString('FirstClass.', $result);
        self::assertStringContainsString('SecondClass.', $result);
        self::assertStringContainsString('@author John Doe', $result);
    }

    public function testMergeWithExistingDocBlockWithClassName(): void
    {
        $code = "<?php /**\n * @license MIT\n */ class TestClass {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'mergeWithExistingDocBlock');

        $this->fixer->configure(['add_structure_name' => true]);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'TestClass');

        $result = $tokens->generateCode();
        self::assertStringContainsString('TestClass.', $result);
        self::assertStringContainsString('@license MIT', $result);
        self::assertStringContainsString('@author John Doe', $result);
    }

    public function testReplaceDocBlockWithClassName(): void
    {
        $code = "<?php /**\n * @license MIT\n */ class TestClass {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'replaceDocBlock');

        $this->fixer->configure(['add_structure_name' => true]);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'TestClass');

        $result = $tokens->generateCode();
        self::assertStringContainsString('TestClass.', $result);
        self::assertStringNotContainsString('@license MIT', $result);
        self::assertStringContainsString('@author John Doe', $result);
    }

    public function testInsertNewDocBlockWithClassNameAndSeparateNone(): void
    {
        $code = '<?php class TestClass {}';
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'insertNewDocBlock');

        $this->fixer->configure([
            'add_structure_name' => true,
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'TestClass');

        $expected = "<?php /**\n * TestClass.\n *\n * @author John Doe\n */class TestClass {}";
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testSkipsAnonymousClasses(): void
    {
        $code = '<?php $obj = new class {};';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        // Code should remain unchanged - no DocBlock added to anonymous class
        self::assertSame($code, $tokens->generateCode());
    }

    public function testSkipsAnonymousClassesButProcessesRegularClasses(): void
    {
        $code = '<?php class RegularClass {} $obj = new class {};';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $result = $tokens->generateCode();

        // Regular class should have DocBlock
        self::assertStringContainsString("/**\n * @author John Doe\n */class RegularClass", $result);
        // Anonymous class should NOT have DocBlock (should remain as "new class")
        self::assertStringContainsString('new class {}', $result);
    }

    public function testIsAnonymousClassDetectsAnonymousClass(): void
    {
        $code = '<?php $obj = new class {};';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'isAnonymousClass');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        self::assertTrue($result);
    }

    public function testIsAnonymousClassReturnsFalseForRegularClass(): void
    {
        $code = '<?php class RegularClass {}';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'isAnonymousClass');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        self::assertFalse($result);
    }

    public function testIsAnonymousClassWithAttribute(): void
    {
        $code = '<?php $obj = new #[SomeAttribute] class {};';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'isAnonymousClass');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        self::assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.3')]
    public function testIsAnonymousClassWithReadonlyModifier(): void
    {
        $code = '<?php $obj = new readonly class {};';
        $tokens = Tokens::fromCode($code);

        $method = new ReflectionMethod($this->fixer, 'isAnonymousClass');

        // Find the class token index
        $classIndex = null;
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if ($tokens[$i]->isGivenKind(\T_CLASS)) {
                $classIndex = $i;
                break;
            }
        }

        $result = $method->invoke($this->fixer, $tokens, $classIndex);

        // This tests line 156: continue when token is T_READONLY or T_FINAL
        self::assertTrue($result);
    }

    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.3')]
    public function testSkipsAnonymousClassWithReadonlyModifier(): void
    {
        $code = '<?php $obj = new readonly class {};';
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'separate' => 'none',
            'ensure_spacing' => false,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        // Anonymous class with readonly modifier should NOT have DocBlock added
        self::assertSame($code, $tokens->generateCode());
    }

    public function testFullRoundTripWithDuplicateImplementsAnnotations(): void
    {
        $parseMethod = new ReflectionMethod($this->fixer, 'parseExistingAnnotations');
        $buildMethod = new ReflectionMethod($this->fixer, 'buildDocBlock');

        // Original DocBlock with duplicate @implements
        $originalDocBlock = "/**\n * @author Konrad Michalik\n * @license GPL-3.0\n * @implements ArrayAccess<int|null, IconImage>\n * @implements IteratorAggregate<int, IconImage>\n */";

        // Parse existing annotations
        $parsed = $parseMethod->invoke($this->fixer, $originalDocBlock);

        // Verify parsing preserved both @implements
        self::assertIsArray($parsed['implements']);
        self::assertCount(2, $parsed['implements']);

        // Build DocBlock from parsed annotations
        $rebuilt = $buildMethod->invoke($this->fixer, $parsed, '');

        // Verify both @implements are present in rebuilt DocBlock
        self::assertStringContainsString('@author Konrad Michalik', $rebuilt);
        self::assertStringContainsString('@license GPL-3.0', $rebuilt);
        self::assertStringContainsString('@implements ArrayAccess<int|null, IconImage>', $rebuilt);
        self::assertStringContainsString('@implements IteratorAggregate<int, IconImage>', $rebuilt);
    }

    public function testMergeWithExistingDocBlockPreservesDuplicateImplements(): void
    {
        $code = "<?php /**\n * @implements ArrayAccess<int, string>\n * @implements IteratorAggregate<int, string>\n */ class TestClass {}";
        $tokens = Tokens::fromCode($code);
        $annotations = ['author' => 'John Doe'];

        $method = new ReflectionMethod($this->fixer, 'mergeWithExistingDocBlock');

        $this->fixer->configure(['preserve_existing' => true]);
        $method->invoke($this->fixer, $tokens, 1, $annotations, 'TestClass');

        $result = $tokens->generateCode();

        // Both @implements should be preserved
        self::assertStringContainsString('@implements ArrayAccess<int, string>', $result);
        self::assertStringContainsString('@implements IteratorAggregate<int, string>', $result);
        self::assertStringContainsString('@author John Doe', $result);
    }

    public function testApplyFixPreservesDuplicateImplementsInExistingDocBlock(): void
    {
        $code = "<?php\n/**\n * @implements ArrayAccess<int, string>\n * @implements IteratorAggregate<int, string>\n */\nclass TestClass {}";
        $tokens = Tokens::fromCode($code);
        $file = new SplFileInfo(__FILE__);

        $method = new ReflectionMethod($this->fixer, 'applyFix');

        $this->fixer->configure([
            'annotations' => ['author' => 'John Doe'],
            'preserve_existing' => true,
        ]);
        $method->invoke($this->fixer, $file, $tokens);

        $result = $tokens->generateCode();

        // Verify both @implements annotations are preserved after merge
        self::assertStringContainsString('@implements ArrayAccess<int, string>', $result);
        self::assertStringContainsString('@implements IteratorAggregate<int, string>', $result);
        self::assertStringContainsString('@author John Doe', $result);
    }
}
