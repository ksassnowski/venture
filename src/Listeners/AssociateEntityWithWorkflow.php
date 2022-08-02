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

namespace Sassnowski\Venture\Listeners;

use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Models\EntityAwareWorkflowInterface;

final class AssociateEntityWithWorkflow
{
    public function __invoke(WorkflowCreating $event): void
    {
        $workflow = $event->definition->workflow();

        if ($workflow instanceof EntityAwareWorkflowInterface) {
            $event->model
                ->workflowable()
                ->associate($workflow->getWorkflowable());
        }
    }
}
