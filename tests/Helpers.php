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

use Illuminate\Support\Str;
use Sassnowski\Venture\Collection\JobDefinitionCollection;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Workflow\JobDefinition;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;

function createWorkflow(array $attributes = []): Workflow
{
    return Workflow::create(\array_merge([
        'job_count' => 0,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ], $attributes));
}

function createWorkflowJob(Workflow $workflow, array $attributes = []): WorkflowJob
{
    return WorkflowJob::create(\array_merge([
        'uuid' => (string) Str::orderedUuid(),
        'name' => '::name::',
        'job' => '::job::',
        'workflow_id' => $workflow->id,
        'failed_at' => null,
        'finished_at' => null,
    ], $attributes));
}

/**
 * @param WorkflowStepInterface[] $jobs
 */
function wrapJobsForWorkflow(array $jobs): JobDefinitionCollection
{
    return collect($jobs)->reduce(function (JobDefinitionCollection $collection, WorkflowStepInterface $job) {
        $definition = new JobDefinition(
            (string) Str::orderedUuid(),
            \get_class($job),
            $job,
        );

        $collection->add($definition);

        return $collection;
    }, new JobDefinitionCollection());
}
