<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Closure;
use Sassnowski\Venture\Models\Workflow;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManager implements WorkflowManagerInterface
{
    private Dispatcher $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function define(string $workflowName): WorkflowDefinition
    {
        return new WorkflowDefinition($workflowName);
    }

    public function startWorkflow(AbstractWorkflow $abstractWorkflow): Workflow
    {
        $definition = $abstractWorkflow->definition();

        [$workflow, $initialJobs] = $definition->build(
            Closure::fromCallable([$abstractWorkflow, 'beforeCreate'])
        );

        collect($initialJobs)->each(function ($job) {
            $this->dispatcher->dispatch($job);
        });

        return $workflow;
    }

    public function job(int $workflowId): ?WorkflowJob
    {
        return Workflow::find($workflowId);
    }
}
