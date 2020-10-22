<?php declare(strict_types=1);

namespace Stubs;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Facades\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

class TestAbstractWorkflow extends AbstractWorkflow
{
    public function definition(): WorkflowDefinition
    {
        return Workflow::define('::name::')
            ->addJob(new TestJob1())
            ->addJob(new TestJob2())
            ->addJob(new TestJob3(), [TestJob1::class]);
    }
}
