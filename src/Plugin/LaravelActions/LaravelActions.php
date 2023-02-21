<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Plugin\LaravelActions;

use Illuminate\Contracts\Foundation\Application;
use Lorisleiva\Actions\ActionManager;
use Sassnowski\Venture\Plugin\Plugin;
use Sassnowski\Venture\Plugin\PluginContext;
use Sassnowski\Venture\StepIdGenerator;

final class LaravelActions implements Plugin
{
    public static function register(Application $app): void
    {
        $app->bind(StepIdGenerator::class, LaravelActionsStepIdGenerator::class);
    }

    public function install(PluginContext $context): void
    {
        ActionManager::useJobDecorator(JobDecorator::class);
        ActionManager::useUniqueJobDecorator(UniqueJobDecorator::class);
    }
}
