<?php declare(strict_types=1);

namespace Stubs;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowWithParameter extends AbstractWorkflow
{
    public string $something;

    public function __construct(string $something)
    {
        $this->something = $something;
    }

    public function definition(): WorkflowDefinition
    {
        return Workflow::define('Star Wars is fantasy, change my mind.')
            ->addJob(new TestJob1());
    }
}
