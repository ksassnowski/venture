<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\Models\Workflow;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\WorkflowDefinition;
use Sassnowski\Venture\Manager\WorkflowManagerInterface as WorkflowManagerContract;

class WorkflowManager implements WorkflowManagerContract
{
    private Dispatcher $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function startWorkflow(WorkflowDefinition $definition): Workflow
    {
        $pendingWorkflow = $definition->definition();

        [$workflow, $initialJobs] = $pendingWorkflow->build();

        collect($initialJobs)->each(function ($job) {
            $this->dispatcher->dispatch($job);
        });

        return $workflow;
    }
}
