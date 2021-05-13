<?php


namespace Stubs;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Facades\Workflow as WorkflowFacade;
use Sassnowski\Venture\WorkflowDefinition;

class NestedWorkflow extends AbstractWorkflow
{
    public function __construct(public $job = null)
    {
        $this->job ??= new TestJob1();
    }

    public function definition(): WorkflowDefinition
    {
        return WorkflowFacade::define('::name::')->addJob($this->job);
    }
}

