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

use Carbon\Carbon;
use Sassnowski\Venture\State\DefaultWorkflowJobState;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;

uses(TestCase::class);

beforeEach(function (): void {
    $this->workflow = createWorkflow();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('is finished if the finished_at timestamp is set', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'finished_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->hasFinished()->toBeTrue();
});

it('is not finished if the finished_at timestamp is null', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'finished_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->hasFinished()->toBeFalse();
});

it('can mark the job as finished', function (): void {
    Carbon::setTestNow(now());
    $workflowJob = createWorkflowJob($this->workflow, [
        'finished_at' => null,
    ]);
    $state = new DefaultWorkflowJobState($workflowJob);

    $state->markAsFinished();

    expect($workflowJob->fresh())
        ->finished_at->timestamp->toBe(now()->timestamp);
});

it('has failed if the failed_at timestamp is set and the job is not finished', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'failed_at' => now(),
        'finished_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->hasFailed()->toBeTrue();
});

it('is not failed if the failed_at timestamp is set but the job is finished', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'failed_at' => now(),
        'finished_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->hasFailed()->toBeFalse();
});

it('is not failed if the failed_at timestamp is null', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'failed_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->hasFailed()->toBeFalse();
});

it('can mark the job as failed', function (): void {
    Carbon::setTestNow(now());
    $workflowJob = createWorkflowJob($this->workflow, [
        'failed_at' => null,
    ]);
    $exception = new Exception('::boom::');
    $state = new DefaultWorkflowJobState($workflowJob);

    $state->markAsFailed($exception);

    expect($workflowJob->fresh())
        ->failed_at->timestamp->toBe(now()->timestamp)
        ->exception->toBe((string) $exception);
});

it('is processing if the started_at column is set and the job has not finished or failed', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => now(),
        'finished_at' => null,
        'failed_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isProcessing()->toBeTrue();
});

it('is not processing if the started_at column is set but the job has finished', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isProcessing()->toBeFalse();
});

it('is not processing if the started_at column is set but the job has failed', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => now(),
        'failed_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isProcessing()->toBeFalse();
});

it('can mark the job as processing', function (): void {
    Carbon::setTestNow(now());
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => null,
    ]);
    $state = new DefaultWorkflowJobState($workflowJob);

    $state->markAsProcessing();

    expect($state)->isProcessing()->toBeTrue();
});

it('is pending if the job is not finished, failed or started', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => null,
        'failed_at' => null,
        'finished_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isPending()->toBeTrue();
});

it('is not pending if the job is processing', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => now(),
        'failed_at' => null,
        'finished_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isPending()->toBeFalse();
});

it('is not pending if the job is finished', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => null,
        'failed_at' => null,
        'finished_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isPending()->toBeFalse();
});

it('is not pending if the job has failed', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'started_at' => null,
        'failed_at' => now(),
        'finished_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isPending()->toBeFalse();
});

it('is gated if the job gated_at column is set', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isGated()->toBeTrue();
});

it('is gated if the job gated_at column is null', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => null,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isGated()->toBeFalse();
});

it('is not gated if the job is not a gated job', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => false,
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isGated()->toBeFalse();
});

it('is not gated if the job has finished', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => now(),
        'finished_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isGated()->toBeFalse();
});

it('is not gated if the job has failed', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => now(),
        'failed_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isGated()->toBeFalse();
});

it('is not gated if the job is processing', function (): void {
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => now(),
        'failed_at' => null,
        'finished_at' => null,
        'started_at' => now(),
    ]);

    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->isGated()->toBeFalse();
});

it('can mark the job as gated', function (): void {
    Carbon::setTestNow(now());
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => null,
    ]);
    $state = new DefaultWorkflowJobState($workflowJob);

    $state->markAsGated();

    expect($workflowJob->fresh())
        ->gated_at->timestamp->toBe(now()->timestamp);
});

it('throws an exception when trying to mark a non-gated job as gated', function (): void {
    Carbon::setTestNow(now());
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => false,
        'gated_at' => null,
    ]);
    $state = new DefaultWorkflowJobState($workflowJob);

    $state->markAsGated();
})->throws(
    RuntimeException::class,
    'Only gated jobs can be marked as gated',
);

it('can run if all dependencies of the job have finished', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob($job = new TestJob3(), [TestJob1::class, TestJob2::class])
        ->build();
    $workflow->update([
        'finished_jobs' => [TestJob1::class, TestJob2::class],
    ]);
    $state = new DefaultWorkflowJobState($job->step());

    expect($state)->canRun()->toBeTrue();
});

it('can not run if some of the job\'s dependencies have not finished yet', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob($job = new TestJob3(), [TestJob1::class, TestJob2::class])
        ->build();
    $workflow->update([
        'finished_jobs' => [TestJob1::class],
    ]);
    $state = new DefaultWorkflowJobState($job->step());

    expect($state)->canRun()->toBeFalse();
});

it('can not run if the job is gated', function (): void {
    Carbon::setTestNow(now());
    $workflowJob = createWorkflowJob($this->workflow, [
        'gated' => true,
        'gated_at' => now(),
    ]);
    $state = new DefaultWorkflowJobState($workflowJob);

    expect($state)->canRun()->toBeFalse();
});
