<div align="center">

![icon](icon.png)

# Php DocBlock Header Fixer

[![Coverage](https://img.shields.io/coverallsCoverage/github/konradmichalik/php-doc-block-header-fixer?logo=coveralls)](https://coveralls.io/github/konradmichalik/php-doc-block-header-fixer)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-doc-block-header-fixer/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/php-doc-block-header-fixer/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-doc-block-header-fixer/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/php-doc-block-header-fixer/actions/workflows/tests.yml)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/php-doc-block-header-fixer/php?logo=php)](https://packagist.org/packages/konradmichalik/php-doc-block-header-fixer)

</div>

PHP-CS-Fixer ships rules for DocBlock content and spacing, but none of them add or enforce header annotations such as `@author` and `@license` before a class, interface, trait or enum. Keeping those in sync by hand means every renamed class or licence change turns into a manual find-and-replace across the codebase. This package adds a configurable PHP-CS-Fixer rule that generates and enforces the annotations you configure, while leaving the rest of an existing DocBlock, descriptions, other tags, ordering untouched.

## ✨ Features

- [**Configurable annotations**](docs/configuration.md): add any tag (`@author`, `@license`, `@template`, `@phpstan-type`, …), each enforced or appended independently
- [**Composer autodiscovery**](docs/usage.md#fromcomposer): `fromComposer()` reads authors and licence straight from `composer.json`, so the header never drifts from the package metadata
- **Preserves existing DocBlocks**: descriptions, other tags and ordering survive; only the configured annotations are enforced
- [**Structure name summaries**](docs/configuration.md#add_structure_name): optionally prepend the class, interface, trait or enum name as the DocBlock's first line, with stale-name replacement on rename
- **PHP-CS-Fixer compatible**: avoids conflicts with `phpdoc_no_package`, `phpdoc_separation` and `no_blank_lines_after_phpdoc`

## 🔥 Installation

### Requirements

- PHP 8.2, 8.3, 8.4 or 8.5
- `ext-tokenizer` (bundled with PHP by default)
- `friendsofphp/php-cs-fixer` ^3.14 (installed automatically as a dependency)

### Composer

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/php-doc-block-header-fixer?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/php-doc-block-header-fixer)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/php-doc-block-header-fixer?color=brightgreen)](https://packagist.org/packages/konradmichalik/php-doc-block-header-fixer)

```bash
composer require --dev konradmichalik/php-doc-block-header-fixer
```

## 🚀 Quick start

Register the fixer and read authors and licence straight from your `composer.json`:

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer(),
    ])
    ->setRules([
        KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader::fromComposer()->toArray(),
    ])
;
```

Running `php-cs-fixer fix` now turns

```php
<?php

class MyClass
{
    public function myMethod(): void {}
}
```

into

```php
<?php
/**
 * MyClass.
 *
 * @author Jane Doe <jane@example.com>
 * @license MIT
 */
class MyClass
{
    public function myMethod(): void {}
}
```

The same happens for interfaces, traits and enums. See [Usage](docs/usage.md) for the plain-array and object-oriented alternatives to `fromComposer()`.

## 📚 Documentation

| Topic | What's inside |
|-------|---------------|
| [Usage](docs/usage.md) | Registering the fixer: plain array, object-oriented builder, and `fromComposer()` autodiscovery |
| [Configuration](docs/configuration.md) | Every option with its type, default and effect |

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE.md).
