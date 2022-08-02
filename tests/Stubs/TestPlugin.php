<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Stubs;

use Sassnowski\Venture\Plugin\PluginContext;
use Sassnowski\Venture\Plugin\PluginInterface;

final class TestPlugin implements PluginInterface
{
    public static int $installCalled = 0;

    public function install(PluginContext $context): void
    {
        ++self::$installCalled;
    }
}
