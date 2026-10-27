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

use Illuminate\Support\Str;
use Stubs\RetryableJob;

uses(TestCase::class);

test('a stale workflow persists a decrement to the locked failed count', function (): void {
    $workflow = createWorkflow(['job_count' => 2]);
    $first = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('first');
    $second = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('second');

    createWorkflowJob($workflow, ['uuid' => $first->getStepId(), 'job' => \serialize($first)]);
    createWorkflowJob($workflow, ['uuid' => $second->getStepId(), 'job' => \serialize($second)]);

    $workflow->markJobAsFailed($first, new RuntimeException('First failed.'));
    $stale = $workflow->fresh();
    $workflow->fresh()->markJobAsFailed($second, new RuntimeException('Second failed.'));

    expect($stale->jobs_failed)->toBe(1);
    expect($workflow->fresh()->jobs_failed)->toBe(2);

    $stale->markJobAsFinished($first);

    expect($workflow->fresh()->jobs_failed)->toBe(1);
    expect($stale->jobs_failed)->toBe(1);
    expect($first->step()->isFinished())->toBeTrue();
    expect($second->step()->hasFailed())->toBeTrue();
});

test('a stale workflow persists an increment to the locked failed count', function (): void {
    $workflow = createWorkflow(['job_count' => 2]);
    $first = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('first');
    $second = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('second');

    createWorkflowJob($workflow, ['uuid' => $first->getStepId(), 'job' => \serialize($first)]);
    createWorkflowJob($workflow, ['uuid' => $second->getStepId(), 'job' => \serialize($second)]);

    $workflow->markJobAsFailed($first, new RuntimeException('First failed.'));
    $stale = $workflow->fresh();
    $workflow->fresh()->markJobAsFinished($first);

    expect($stale->jobs_failed)->toBe(1);
    expect($workflow->fresh()->jobs_failed)->toBe(0);

    $stale->markJobAsFailed($second, new RuntimeException('Second failed.'));

    expect($workflow->fresh()->jobs_failed)->toBe(1);
    expect($stale->jobs_failed)->toBe(1);
    expect($second->step()->hasFailed())->toBeTrue();
});

test('a stale workflow persists completion after another instance resets its progress', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 1,
        'finished_jobs' => ['first'],
    ]);
    $job = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('first');
    $step = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'job' => \serialize($job),
        'finished_at' => now(),
    ]);

    $stale = $workflow->fresh();
    $workflow->fresh()->update(['jobs_processed' => 0, 'finished_jobs' => []]);
    $step->update(['finished_at' => null]);

    $stale->markJobAsFinished($job);

    expect($workflow->fresh()->jobs_processed)->toBe(1);
    expect($workflow->fresh()->finished_jobs)->toBe(['first']);
    expect($stale->jobs_processed)->toBe(1);
    expect($stale->finished_jobs)->toBe(['first']);
    expect($step->fresh()->isFinished())->toBeTrue();
});

test('independently loaded workflows preserve both completed steps', function (): void {
    $workflow = createWorkflow(['job_count' => 2]);
    $first = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('first');
    $second = (new RetryableJob())->withWorkflowId($workflow->id)->withStepId(Str::uuid())->withJobId('second');

    createWorkflowJob($workflow, ['uuid' => $first->getStepId(), 'job' => \serialize($first)]);
    createWorkflowJob($workflow, ['uuid' => $second->getStepId(), 'job' => \serialize($second)]);

    $firstWorker = $workflow->fresh();
    $secondWorker = $workflow->fresh();

    $firstWorker->markJobAsFinished($first);
    $secondWorker->markJobAsFinished($second);

    expect($workflow->fresh()->jobs_processed)->toBe(2);
    expect($workflow->fresh()->finished_jobs)->toBe(['first', 'second']);
    expect($secondWorker->jobs_processed)->toBe(2);
    expect($secondWorker->finished_jobs)->toBe(['first', 'second']);
    expect($workflow->fresh()->hasRan())->toBeTrue();
});
