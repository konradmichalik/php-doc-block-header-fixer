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

namespace KonradMichalik\PhpDocBlockHeaderFixer\Generators;

/**
 * Generator.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-3.0-or-later
 */
interface Generator
{
    /**
     * @deprecated implement toArray() as well, it replaces this method in the next breaking release
     *
     * @return array<string, array<string, mixed>>
     */
    public function __toArray(): array;
}
