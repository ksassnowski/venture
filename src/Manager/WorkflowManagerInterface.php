<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\AbstractWorkflow;

interface WorkflowManagerInterface
{
    public function startWorkflow(AbstractWorkflow $abstractWorkflow): Workflow;
}
