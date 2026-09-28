# Usage

Register the fixer in `.php-cs-fixer.php` and configure `KonradMichalik/docblock_header_comment` with your annotations. Three ways to build that configuration produce the same result; pick whichever fits your config file.

## Plain array

The most explicit option: the array goes straight into `setRules()`.

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer(),
    ])
    ->setRules([
        'KonradMichalik/docblock_header_comment' => [
            'annotations' => [
                'author' => 'Jane Doe <jane@example.com>',
                'license' => 'MIT',
            ],
            'preserve_existing' => true,
            'separate' => 'none',
            'add_structure_name' => true,
        ],
    ])
;
```

## Object-oriented builder

`DocBlockHeader::create()` validates and normalizes the annotations when the config file runs, not when PHP-CS-Fixer resolves the array, and its named arguments get IDE autocomplete.

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer(),
    ])
    ->setRules([
        KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader::create(
            [
                'author' => 'Jane Doe <jane@example.com>',
                'license' => 'MIT',
            ],
            preserveExisting: true,
            separate: \KonradMichalik\PhpDocBlockHeaderFixer\Enum\Separate::None,
            addStructureName: true,
        )->toArray(),
    ])
;
```

## `fromComposer()`

Reads `author` (from the `authors` array) and `license` from `composer.json`, so the DocBlock header never drifts out of sync with the package metadata. `composer.json` has no field for tags like `@template`, so `$additionalAnnotations` lets you add them on top of the autodiscovered `author` and `license`, here adding `@template T` to a generic class:

```php
<?php
// ...
return (new PhpCsFixer\Config())
    // ...
    ->registerCustomFixers([
        new KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer(),
    ])
    ->setRules([
        KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader::fromComposer(
            additionalAnnotations: ['template' => 'T'],
        )->toArray(),
    ])
;
```

All three accept any syntactically valid tag, not only `author` and `license`, for example `template` or `phpstan-type`. `create()` and `fromComposer()` share the same options: see [Configuration](configuration.md) for every one of them.
