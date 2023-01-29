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

namespace Sassnowski\Venture\Plugin;

use Sassnowski\Venture\Listeners\AssociateEntityWithWorkflow;

final class EntityAwareWorkflows implements Plugin
{
    public function install(PluginContext $context): void
    {
        $context->onWorkflowCreating(AssociateEntityWithWorkflow::class);
    }
}
