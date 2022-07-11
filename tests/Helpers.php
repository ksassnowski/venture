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

use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestWorkflow;

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

function wrapJobsForWorkflow($jobs)
{
    return collect($jobs)->map(fn ($job) => [
        'job' => $job->withJobId($job->jobId ?? \get_class($job)),
        'name' => \get_class($job),
    ])->all();
}

function createQueueJob(object $command, bool $failed = false, bool $released = false): Job
{
    return with(Mockery::mock(Job::class), function (MockInterface $jobMock) use ($command, $failed, $released) {
        $jobMock->allows('payload')
            ->andReturns([
                'data' => [
                    'command' => \serialize($command),
                ],
            ]);
        $jobMock->allows('hasFailed')->andReturn($failed);
        $jobMock->allows('isReleased')->andReturn($released);
        $jobMock->allows('delete');

        return $jobMock;
    });
}

function createDefinition(string $name = '', ?AbstractWorkflow $workflow = null): WorkflowDefinition
{
    return new WorkflowDefinition(
        $workflow ?: new TestWorkflow(),
        $name,
    );
}
