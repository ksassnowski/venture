<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use Carbon\Carbon;
use Illuminate\Support\Str;
use Sassnowski\Venture\State\DefaultWorkflowState;
use Sassnowski\Venture\State\WorkflowStateStore;
use Sassnowski\Venture\WorkflowableJob;
use Stubs\NestedWorkflow;
use Stubs\TestJob1;
use Stubs\WorkflowWithWorkflow;

uses(TestCase::class);

beforeEach(function (): void {
    WorkflowStateStore::fake();
});

it('stores a finished job\'s id', function (WorkflowableJob $job, string $expectedJobId): void {
    $workflow = createWorkflow([
        'jobs_processed' => '[]',
    ]);
    $state = new DefaultWorkflowState($workflow);

    $state->markJobAsFinished($job);

    expect($workflow->refresh())
        ->finished_jobs->toEqual([$expectedJobId]);
})->with([
    'no job id should default to class name' => [
        new TestJob1(),
        TestJob1::class,
    ],
    'use existing job id' => [
        (new TestJob1())->withJobId('::job-id::'),
        '::job-id::',
    ],
]);

it('it stores finished job id for nested workflow jobs', function (): void {
    $workflow = new WorkflowWithWorkflow(new NestedWorkflow(
        $job = new TestJob1(),
    ));
    $definition = $workflow->getDefinition();
    [$model, $initial] = $definition->build();
    $state = new DefaultWorkflowState($model);

    $state->markJobAsFinished($job);

    expect($model->refresh())
        ->finished_jobs->toEqual([NestedWorkflow::class . '.' . TestJob1::class]);
});

it('it increments the finished jobs count when a job finished', function (): void {
    $job1 = new TestJob1();
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
    ]);
    $state = new DefaultWorkflowState($workflow);

    $state->markJobAsFinished($job1);

    expect($workflow->refresh())
        ->jobs_processed->toBe(1);
});

it('returns true if all jobs a workflow have finished', function (): void {
    $workflow = createWorkflow([
        'job_count' => 2,
        'jobs_processed' => 2,
    ]);
    $state = new DefaultWorkflowState($workflow);

    expect($state)->allJobsHaveFinished()->toBeTrue();
});

it('returns false if not all jobs of a workflow have finished', function (): void {
    $workflow = createWorkflow([
        'job_count' => 2,
        'jobs_processed' => 1,
    ]);
    $state = new DefaultWorkflowState($workflow);

    expect($state)->allJobsHaveFinished()->toBeFalse();
});

it('marks the job itself as finished', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
    ]);
    $job = (new TestJob1())->withStepId(Str::orderedUuid());
    $workflowJob = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'job' => \serialize($job),
    ]);
    $state = new DefaultWorkflowState($workflow);

    $state->markJobAsFinished($job);

    expect($workflowJob->refresh())
        ->isFinished()->toBeTrue();
});

it('is finished if the finished_at timestamp is set', function (): void {
    $workflow = createWorkflow(['finished_at' => now()]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->isFinished()->toBeTrue();
});

it('is not finished if the finished_at timestamp is not set', function (): void {
    $workflow = createWorkflow(['finished_at' => null]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->isFinished()->toBeFalse();
});

it('can mark the workflow as finished', function (): void {
    Carbon::setTestNow(now());
    $workflow = createWorkflow(['finished_at' => null]);
    $state = new DefaultWorkflowState($workflow);

    $state->markAsFinished();

    expect($workflow->fresh())
        ->finished_at->timestamp->toBe(now()->timestamp);
});

it('is cancelled if the cancelled_at timestamp is set', function (): void {
    $workflow = createWorkflow(['cancelled_at' => now()]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->isCancelled()->toBeTrue();
});

it('is not cancelled if the cancelled_at timestamp is null', function (): void {
    $workflow = createWorkflow(['cancelled_at' => null]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->isCancelled()->toBeFalse();
});

it('can mark the workflow as cancelled', function (): void {
    Carbon::setTestNow(now());
    $workflow = createWorkflow(['cancelled_at' => null]);
    $state = new DefaultWorkflowState($workflow);

    $state->markAsCancelled();

    expect($workflow->fresh())
        ->cancelled_at->timestamp->toBe(now()->timestamp);
});

it('calculates the remaining jobs', function (): void {
    $workflow = createWorkflow([
        'job_count' => 5,
        'jobs_processed' => 3,
    ]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->remainingJobs()->toBe(2);
});

it('has ran if all jobs have either finished or failed', function (): void {
    $workflow = createWorkflow([
        'job_count' => 5,
        'jobs_processed' => 3,
        'jobs_failed' => 2,
    ]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->hasRan()->toBeTrue();
});

it('has not run if there are still jobs that haven\'t been processed', function (): void {
    $workflow = createWorkflow([
        'job_count' => 5,
        'jobs_processed' => 3,
        'jobs_failed' => 1,
    ]);

    $state = new DefaultWorkflowState($workflow);

    expect($state)->hasRan()->toBeFalse();
});

it('cancelling an already cancelled job does not update timestamp', function (): void {
    Carbon::setTestNow(now());
    $workflow = createWorkflow(['cancelled_at' => now()->subDay()]);
    $state = new DefaultWorkflowState($workflow);

    $state->markAsCancelled();

    expect($workflow)
        ->cancelled_at->timestamp->toBe(now()->subDay()->timestamp);
});

it('increments the failed job count', function (): void {
    $workflow = createWorkflow(['jobs_failed' => 0]);
    $state = new DefaultWorkflowState($workflow);

    $state->markJobAsFailed(new TestJob1(), new Exception());

    expect($workflow->refresh())
        ->jobs_failed->toBe(1);
});

it('marks the step as failed', function (): void {
    $workflow = createWorkflow();
    $job = (new TestJob1())->withStepId(Str::orderedUuid());
    createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'job' => \serialize($job),
    ]);
    $state = new DefaultWorkflowState($workflow);
    $exception = new Exception();

    $state->markJobAsFailed($job, $exception);

    expect(WorkflowStateStore::forJob(TestJob1::class))
        ->hasFailed()->toBeTrue()
        ->exception->toBe($exception);
});
