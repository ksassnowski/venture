<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Closure;
use Sassnowski\Venture\Models\Workflow;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\WorkflowDefinition;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use Sassnowski\Venture\Workflow\LegacyWorkflowStepAdapter;

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

        collect($initialJobs)->each(function (WorkflowStepInterface $job) {
            if ($job instanceof LegacyWorkflowStepAdapter) {
                $job = $job->getWrappedJob();
            }

            $this->dispatcher->dispatch($job);
        });

        return $workflow;
    }
}
