<?php


namespace Stubs;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Facades\Workflow as WorkflowFacade;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowWithWorkflow extends AbstractWorkflow
{
    public function __construct(public $workflow)
    {
    }

    public function definition(): WorkflowDefinition
    {
        return WorkflowFacade::define('::name::')
            ->addWorkflow($this->workflow);
    }
}

