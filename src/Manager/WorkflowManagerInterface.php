<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

interface WorkflowManagerInterface
{
    public function define(AbstractWorkflow $workflow, string $workflowName): WorkflowDefinition;

    public function startWorkflow(AbstractWorkflow $abstractWorkflow, ?string $connection): Workflow;
}
