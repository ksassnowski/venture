<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

interface WorkflowManagerInterface
{
    public function startWorkflow(WorkflowDefinition $definition): Workflow;
}
