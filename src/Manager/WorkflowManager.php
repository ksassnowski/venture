<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\Models\Workflow;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Manager\WorkflowManagerInterface as WorkflowManagerContract;

class WorkflowManager implements WorkflowManagerContract
{
    private Dispatcher $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function startWorkflow(AbstractWorkflow $abstractWorkflow): Workflow
    {
        $definition = $abstractWorkflow->definition();

        [$workflow, $initialJobs] = $definition->build();

        collect($initialJobs)->each(function ($job) {
            $this->dispatcher->dispatch($job);
        });

        return $workflow;
    }
}
