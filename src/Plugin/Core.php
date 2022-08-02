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

namespace Sassnowski\Venture\Plugin;

use Sassnowski\Venture\Listeners\ConfigureNestedWorkflow;
use Sassnowski\Venture\Listeners\ConfigureNewWorkflowJob;
use Sassnowski\Venture\Listeners\SetWorkflowJobProperties;

final class Core implements PluginInterface
{
    public function install(PluginContext $context): void
    {
        $context->onJobAdding(ConfigureNewWorkflowJob::class);
        $context->onWorkflowAdding(ConfigureNestedWorkflow::class);
        $context->onWorkflowCreated(SetWorkflowJobProperties::class);
    }
}
