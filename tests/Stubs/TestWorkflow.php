<?php declare(strict_types=1);

namespace Stubs;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\PendingWorkflow;
use Sassnowski\Venture\WorkflowDefinition;

class TestWorkflow extends WorkflowDefinition
{
    public function definition(): PendingWorkflow
    {
        return Workflow::new('::name::')
            ->addJob(new TestJob1())
            ->addJob(new TestJob2())
            ->addJob(new TestJob3(), [TestJob1::class]);
    }
}
