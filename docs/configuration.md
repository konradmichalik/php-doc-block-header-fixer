# Configuration options

Every option `KonradMichalik/docblock_header_comment` accepts, whichever of the three ways from [Usage](usage.md) builds the array. `DocBlockHeader::create()` and `::fromComposer()` expose the same options as named parameters in camelCase (`preserveExisting`, `addStructureName`, …).

## `annotations`

📝 Name: `annotations` · 🎨 Type: `array<string, string|list<string>|array{value, strategy}|null>` · 🐝 Default: `[]`

DocBlock annotations to add to classes, interfaces, traits and enums. A value is a string, a list of strings (repeats the tag once per entry) or `null` for a bare tag such as `@internal`.

```php
'annotations' => [
    'author' => 'Jane Doe <jane@example.com>',
    'internal' => null,
    'phpstan-type' => ['TFoo', 'TBar'],
],
```

By default a configured tag is enforced: an existing occurrence is rewritten to the configured value and duplicates of the same tag collapse into one. To keep foreign entries instead, use the strategy form:

```php
'annotations' => [
    'author' => ['value' => 'Jane Doe <jane@example.com>', 'strategy' => 'append'],
],
```

`append` adds the configured value only if it is missing and leaves every other `@author` line alone, useful when a file already lists other authors.

## `preserve_existing`

📝 Name: `preserve_existing` · 🎨 Type: `bool` · 🐝 Default: `true`

Keeps everything the configuration does not mention: descriptions, other annotations, ordering. The configured annotations themselves are still enforced (unless their strategy is `append`).

One exception: an annotation sitting on the opening (`/**`) or closing (`*/`) line of an existing multi-line DocBlock is left alone, rewriting it would take the delimiter with it. A single-line DocBlock such as `/** Handles requests. */` is expanded first, so its content is kept and enforced like any other line.

```php
'preserve_existing' => false, // discard the existing DocBlock and rebuild it from the configuration
```

> [!WARNING]
> With `false`, every tag the configuration does not name is lost, including tags that static analysis depends on, such as `@template`, `@extends` and `@implements`.

## `separate`

📝 Name: `separate` · 🎨 Type: `'top'|'bottom'|'both'|'none'` · 🐝 Default: `'none'`

Blank lines around a new DocBlock: `top` adds one before it, `bottom` adds one after it, `both` does both, `none` adds neither.

```php
'separate' => 'top',
```

> [!NOTE]
> `no_blank_lines_after_phpdoc` (part of `@Symfony`) removes the `bottom` blank line again.

## `add_structure_name`

📝 Name: `add_structure_name` · 🎨 Type: `bool` · 🐝 Default: `false`

Adds the structure name as the DocBlock's first line:

```php
/**
 * MyClass.
 *
 * @author Jane Doe <jane@example.com>
 */
class MyClass
```

If the existing first line is something else, the name is prepended above it rather than replacing it.

## `replace_stale_structure_name`

📝 Name: `replace_stale_structure_name` · 🎨 Type: `bool` · 🐝 Default: `false`

With `add_structure_name`, rewrites an existing first line that is a bare identifier followed by a dot (`OldName.`) instead of prepending, so a renamed class does not accumulate its former name.

```php
'add_structure_name' => true,
'replace_stale_structure_name' => true,
```

> [!WARNING]
> A one-word description such as `Deprecated.` or `Helper.` is indistinguishable from a former name and gets rewritten too.

## `ensure_spacing`

📝 Name: `ensure_spacing` · 🎨 Type: `bool` · 🐝 Default: `true`

Moves the structure onto its own line after an existing DocBlock that shares its line, e.g. `/** @internal */ final class Foo`. A newly inserted DocBlock is always followed by a newline regardless of this option.

## Interplay with other fixers

`general_phpdoc_annotation_remove` runs before this fixer. If both name the same tag, the remove rule deletes it and this fixer adds it back after the other tags of the DocBlock. The tag stays, moves to the end of the DocBlock on the first run, and later runs change nothing. Drop the tag from one of the two rules.

```php
'general_phpdoc_annotation_remove' => ['annotations' => ['author']], // removes @author, then the next rule adds it back
'KonradMichalik/docblock_header_comment' => ['annotations' => ['author' => 'Jane Doe']],
```

## Known limitations

A DocBlock at the top of a file counts as the class DocBlock when nothing but whitespace separates it from the class. The two cannot be told apart, so the configured annotations are enforced inside the header. With `'license' => 'MIT'`, the `@license proprietary` below is rewritten to `@license MIT`:

```php
<?php
/**
 * @license proprietary
 */
class Foo {}
```

A `namespace`, `declare` or `use` statement in between keeps the header untouched, and the class gets its own DocBlock.
