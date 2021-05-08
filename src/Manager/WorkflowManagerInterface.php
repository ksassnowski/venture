<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\WorkflowDefinition;

interface WorkflowManagerInterface
{
    public function define(string $workflowName): WorkflowDefinition;

    public function startWorkflow(AbstractWorkflow $abstractWorkflow): Workflow;

    public function completeJob(int $jobId): void;
}
