<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Closure;
use Sassnowski\Venture\Models\Workflow;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\StepIdGenerator;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManager implements WorkflowManagerInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
        private StepIdGenerator $stepIdGenerator,
    ) {
    }

    public function define(string $workflowName): WorkflowDefinition
    {
        return new WorkflowDefinition($workflowName, $this->stepIdGenerator);
    }

    public function startWorkflow(AbstractWorkflow $abstractWorkflow): Workflow
    {
        $definition = $abstractWorkflow->definition();

        [$workflow, $initialJobs] = $definition->build(
            Closure::fromCallable([$abstractWorkflow, 'beforeCreate'])
        );

        collect($initialJobs)->each(function (object $job) {
            $this->dispatcher->dispatch($job);
        });

        return $workflow;
    }
}
