<div align="center">

# Php DocBlock Header Fixer

[![Coverage](https://img.shields.io/coverallsCoverage/github/konradmichalik/php-doc-block-header-fixer?logo=coveralls)](https://coveralls.io/github/konradmichalik/php-doc-block-header-fixer)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-doc-block-header-fixer/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/php-doc-block-header-fixer/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-doc-block-header-fixer/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/php-doc-block-header-fixer/actions/workflows/tests.yml)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/php-doc-block-header-fixer/php?logo=php)](https://packagist.org/packages/konradmichalik/php-doc-block-header-fixer)

</div>

This packages contains a [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) rule to automatically fix the header regarding PHP DocBlocks for classes, interfaces, traits and enums.

**Before:**

```php
<?php

class MyClass
{
    public function myMethod()
    {
        // ...
    }
}

interface MyInterface {}
trait MyTrait {}
enum MyEnum {}
```

**After:**

```php
<?php
/**
 * MyClass.
 *
 * @author Your Name <your@email.org>
 * @license GPL-3.0-or-later
 */
class MyClass
{
    // ...
}
```

## 🔥 Installation

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/php-doc-block-header-fixer?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/php-doc-block-header-fixer)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/php-doc-block-header-fixer?color=brightgreen)](https://packagist.org/packages/konradmichalik/php-doc-block-header-fixer)


```bash
composer require --dev konradmichalik/php-doc-block-header-fixer
```

## ⚡ Usage

Add the PHP-CS-Fixer rule in your `.php-cs-fixer.php` file:

> [!NOTE]
> This fixer is compatible with standard PHP-CS-Fixer rules. It avoids adding annotations that conflict with rules like `phpdoc_no_package` and follows spacing conventions compatible with `phpdoc_separation`.

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer()
    ])
    ->setRules([
        'KonradMichalik/docblock_header_comment' => [
            'annotations' => [
                'author' => 'Konrad Michalik <hej@konradmichalik.dev>',
                'license' => 'GPL-3.0-or-later',
            ],
            'preserve_existing' => true,
            'separate' => 'none',
            'add_structure_name' => true,
        ],
    ])
;
```

Alternatively, you can use a object-oriented configuration:

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer()
    ])
    ->setRules([
        KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader::create(
            [
                'author' => 'Konrad Michalik <hej@konradmichalik.dev>',
                'license' => 'GPL-3.0-or-later',
            ],
            preserveExisting: true,
            separate: \KonradMichalik\PhpDocBlockHeaderFixer\Enum\Separate::None,
            addStructureName: true
        )->__toArray()
    ])
;
```

Or even simpler, automatically read all authors and license from your `composer.json`:

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer()
    ])
    ->setRules([
        KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader::fromComposer()->__toArray()
    ])
;
```

## ⚙️ Configuration

- `annotations` (array): DocBlock annotations to add to classes
- `preserve_existing` (boolean, default: true): Keep existing DocBlock annotations
- `separate` (string, default: 'none'): Add blank lines ('top', 'bottom', 'both', 'none')
- `add_structure_name` (boolean, default: false): Add class name as first line in DocBlock
- `ensure_spacing` (boolean, default: true): Ensure proper spacing after DocBlocks to prevent conflicts with PHP-CS-Fixer rules

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE.md).
