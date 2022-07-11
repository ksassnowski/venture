<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Plugin;

use Illuminate\Support\Str;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\Events\WorkflowCreated;
use Sassnowski\Venture\StepIdGenerator;

final class Core implements Plugin
{
    public function __construct(private StepIdGenerator $stepIdGenerator)
    {
    }

    public function install(PluginContext $context): void
    {
        $context->onJobAdding([$this, 'onJobAdding']);
        $context->onWorkflowAdding([$this, 'onWorkflowAdding']);
        $context->onWorkflowCreated([$this, 'onWorkflowCreated']);
    }

    public function onJobAdding(JobAdding $event): void
    {
        $job = $event->job;
        $event->name ??= $job::class;

        if (null === $job->jobId) {
            $jobID = $event->jobID ?: $this->stepIdGenerator->generateId($event->job);
            $job->withJobId($jobID);
        }

        if (null === $job->stepId) {
            $job->withStepId(Str::orderedUuid());
        }

        if (\method_exists($job, 'delay')) {
            $job->delay($event->delay);
        }
    }

    public function onWorkflowAdding(WorkflowAdding $event): void
    {
        $event->workflowID ??= $this->stepIdGenerator->generateId(
            $event->nestedDefinition->workflow(),
        );

        foreach ($event->nestedDefinition->jobs() as $jobID => $job) {
            $job['job']->withJobId($event->workflowID . '.' . $jobID);
        }
    }

    public function onWorkflowCreated(WorkflowCreated $event): void
    {
        $graph = $event->definition->graph();

        foreach ($event->definition->jobs() as $jobID => $job) {
            $job['job']
                ->withWorkflowId($event->model->id)
                ->withDependantJobs($graph->getDependantJobs($jobID))
                ->withDependencies($graph->getDependencies($jobID));
        }
    }
}
