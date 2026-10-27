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

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Stubs\RetryableJob;

uses(TestCase::class);

test('a successful retry removes the step from the workflow failed count', function (): void {
    $workflow = createWorkflow(['job_count' => 1]);

    $job = (new RetryableJob())
        ->withWorkflowId($workflow->id)
        ->withStepId(Str::uuid())
        ->onConnection('sync');

    $step = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'name' => $job->getJobId(),
        'job' => App::make(WorkflowJobSerializer::class)->serialize($job),
    ]);

    $workflow->markJobAsFailed($job, new RuntimeException('Original failure.'));
    $step->refresh();

    expect($workflow->fresh()->jobs_failed)->toBe(1);

    $step->retry();

    $step->refresh();
    $workflow->refresh();

    expect($step->isFinished())->toBeTrue();
    expect($step->failed_at)->toBeNull();
    expect($step->exception)->toBeNull();
    expect($workflow->jobs_processed)->toBe(1);
    expect($workflow->isFinished())->toBeTrue();
    expect($workflow->jobs_failed)->toBe(0);
    expect($workflow->hasRan())->toBeTrue();
});

test('a successful retry preserves the count of other failed steps', function (): void {
    $workflow = createWorkflow(['job_count' => 2]);

    $job = (new RetryableJob())
        ->withWorkflowId($workflow->id)
        ->withStepId(Str::uuid())
        ->onConnection('sync');

    $step = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'name' => $job->getJobId(),
        'job' => App::make(WorkflowJobSerializer::class)->serialize($job),
    ]);

    $otherJob = (new RetryableJob())
        ->withWorkflowId($workflow->id)
        ->withStepId(Str::uuid());

    $otherStep = createWorkflowJob($workflow, [
        'uuid' => $otherJob->getStepId(),
        'name' => $otherJob->getJobId(),
        'job' => App::make(WorkflowJobSerializer::class)->serialize($otherJob),
    ]);

    $workflow->markJobAsFailed($job, new RuntimeException('Original failure.'));
    $workflow->markJobAsFailed($otherJob, new RuntimeException('Other failure.'));

    $step->refresh()->retry();
    $workflow->refresh();

    expect($otherStep->fresh()->hasFailed())->toBeTrue();
    expect($workflow->jobs_processed)->toBe(1);
    expect($workflow->jobs_failed)->toBe(1);
    expect($workflow->hasRan())->toBeTrue();
});

test('repeated failed retries only count the failed step once', function (): void {
    $workflow = createWorkflow(['job_count' => 1]);

    $job = (new RetryableJob(shouldFail: true))
        ->withWorkflowId($workflow->id)
        ->withStepId(Str::uuid())
        ->onConnection('sync');

    $step = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'name' => $job->getJobId(),
        'job' => App::make(WorkflowJobSerializer::class)->serialize($job),
    ]);

    $workflow->markJobAsFailed($job, new RuntimeException('Original failure.'));

    for ($attempt = 0; 2 > $attempt; ++$attempt) {
        $step->refresh();

        expect(fn () => $step->retry())->toThrow(RuntimeException::class, 'The retry failed.');
        expect($step->fresh()->hasFailed())->toBeTrue();
        expect($step->fresh()->exception)->toContain('The retry failed.');
        expect($workflow->fresh()->jobs_failed)->toBe(1);
    }

    expect($workflow->fresh()->jobs_processed)->toBe(0);
    expect($workflow->fresh()->hasRan())->toBeTrue();
});
