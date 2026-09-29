# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

A PHP CS Fixer custom rule library. It provides the `DocBlockHeaderFixer` rule, which adds configurable DocBlock annotations (author, license, and similar) before class-like declarations.

- Package: `konradmichalik/php-doc-block-header-fixer`, license GPL-3.0-or-later
- PHP `~8.2 || ~8.3 || ~8.4 || ~8.5`, `friendsofphp/php-cs-fixer` `^3.14`, `ext-tokenizer`

## Structure

- `src/Rules/DocBlockHeaderFixer.php`: the fixer, extends `AbstractFixer` and implements `ConfigurableFixerInterface`
- `src/Generators/`: `DocBlockHeader` and `Generator`, build a fixer configuration (for example from `composer.json`)
- `src/Service/`: `AnnotationService`, `ComposerService`
- `src/Enum/Separate.php`: blank-line separation options
- `tests/src/`: PHPUnit tests mirroring `src/` (namespace `KonradMichalik\PhpDocBlockHeaderFixer\Tests\`)
- `docs/`: `configuration.md` and `usage.md`

The fixer works on PHP CS Fixer's token system (`PhpCsFixer\Tokenizer\Tokens`). It merges into an existing DocBlock, replaces it, or inserts a new one. Options: `annotations`, `preserve_existing`, `separate`, `add_structure_name`, `replace_stale_structure_name`, `ensure_spacing`.

## Development commands

```bash
composer install
composer lint          # composer normalize --dry-run, editorconfig-cli (ec), php-cs-fixer --dry-run
composer fix           # same tools, applying fixes
composer sca:php       # phpstan analyse --memory-limit=2G
composer migration     # rector process -c rector.php
```

## Testing

```bash
composer test            # phpunit without coverage
composer test:coverage   # XDEBUG_MODE=coverage phpunit, reports to .build/coverage/
```

Locally, coverage needs Xdebug. CI runs the tests through the shared reusable workflow `tests-php.yml` on PHP 8.2, 8.3, 8.4 and 8.5, and lint plus static analysis through the shared `cgl.yml`. Both are thin wrappers, there is no inline CI logic here.

## Code style and static analysis

- PHP CS Fixer uses `konradmichalik/php-cs-fixer-preset` (`.php-cs-fixer.php`), including file headers from `composer.json` and this package's own `DocBlockHeaderFixer`
- PHPStan level 8 over `src/` and `tests/src/`, with the Symfony and PHPUnit extensions
- Rector with `LevelSetList::UP_TO_PHP_82`, PHP 8.2 target, over `src/` and `tests/`
- EditorConfig is enforced through `ec` (`.editorconfig`)
- Every PHP file declares `declare(strict_types=1);`
- If an option or behavior of the fixer changes, update `docs/configuration.md` and `docs/usage.md`

## Git workflow

- Commit format: `<type>: <description>`
- Types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`
- No co-author trailers
- One commit per logical change
