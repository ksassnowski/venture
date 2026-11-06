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

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Sassnowski\Venture\Dispatcher\QueueDispatcher;
use Sassnowski\Venture\Exceptions\JobAlreadyStartedException;
use Sassnowski\Venture\Models\WorkflowJob;
use Stubs\RetryableJob;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;

uses(TestCase::class);

test('independently loaded steps cannot dispatch the same job twice', function (): void {
    Bus::fake();

    [$workflow] = createDefinition()->addJob(new TestJob1())->build();
    $first = $workflow->jobs()->sole();
    $second = $workflow->jobs()->sole();

    $first->start();

    expect(fn () => $second->start())->toThrow(JobAlreadyStartedException::class);
    Bus::assertDispatchedTimes(TestJob1::class, 1);
});

test('a stale step cannot dispatch a job that has already finished', function (): void {
    Bus::fake();

    [$workflow] = createDefinition()->addJob(new TestJob1())->build();
    $first = $workflow->jobs()->sole();
    $second = $workflow->jobs()->sole();

    $first->start();
    $workflow->markJobAsFinished($first->step());

    expect(fn () => $second->start())->toThrow(JobAlreadyStartedException::class);
    expect($first->fresh()->isFinished())->toBeTrue();
    Bus::assertDispatchedTimes(TestJob1::class, 1);
});

test('concurrent dependency dispatch attempts queue their shared dependent job once', function (): void {
    Bus::fake();

    [$workflow] = createDefinition()
        ->addJob($first = new TestJob1())
        ->addJob($second = new TestJob2())
        ->addJob(new TestJob3(), [TestJob1::class, TestJob2::class])
        ->build();

    $workflow->markJobAsFinished($first);
    $workflow->markJobAsFinished($second);

    $dependent = $workflow->jobs()->where('name', TestJob3::class)->sole();
    $selectDependent = WorkflowJob::query()->whereIn('uuid', [$dependent->uuid])->toSql();
    $dispatchOtherWorker = true;
    $dispatcher = new QueueDispatcher();

    // The first worker has read the pending row, but has not hydrated it yet.
    DB::listen(function (QueryExecuted $query) use (&$dispatchOtherWorker, $selectDependent, $dispatcher, $second): void {
        if ($dispatchOtherWorker && $query->sql === $selectDependent) {
            $dispatchOtherWorker = false;
            $dispatcher->dispatchDependentJobs($second);
        }
    });

    $dispatcher->dispatchDependentJobs($first);

    expect($dispatchOtherWorker)->toBeFalse();
    expect($dependent->fresh()->isProcessing())->toBeTrue();
    Bus::assertDispatchedTimes(TestJob3::class, 1);
});

test('a failed enqueue leaves the step available for another dispatch attempt', function (): void {
    [$workflow] = createDefinition()->addJob(new TestJob1())->build();
    $step = $workflow->jobs()->sole();
    $dispatcher = Bus::getFacadeRoot();

    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable.'));

    expect(fn () => $step->start())->toThrow(RuntimeException::class, 'Queue unavailable.');
    expect($step->fresh()->isPending())->toBeTrue();

    Bus::swap($dispatcher);
    Bus::fake();

    $step->start();

    expect($step->fresh()->isProcessing())->toBeTrue();
    Bus::assertDispatchedTimes(TestJob1::class, 1);
});

test('starting a synchronous step preserves its completed state', function (): void {
    [$workflow, $jobs] = createDefinition()
        ->addJob((new RetryableJob())->onConnection('sync'))
        ->build();

    (new QueueDispatcher())->dispatch($jobs);

    expect($workflow->jobs()->sole()->isFinished())->toBeTrue();
    expect($workflow->fresh()->jobs_processed)->toBe(1);
    expect($workflow->fresh()->isFinished())->toBeTrue();
});

test('starting a failing synchronous step preserves its failure state', function (): void {
    [$workflow, $jobs] = createDefinition()
        ->addJob((new RetryableJob(shouldFail: true))->onConnection('sync'))
        ->build();

    expect(fn () => (new QueueDispatcher())->dispatch($jobs))
        ->toThrow(RuntimeException::class, 'The retry failed.');

    $step = $workflow->jobs()->sole();

    expect($step->hasFailed())->toBeTrue();
    expect($step->exception)->toContain('The retry failed.');
    expect($workflow->fresh()->jobs_failed)->toBe(1);
    expect($workflow->fresh()->jobs_processed)->toBe(0);
});

test('starting a failed step preserves successful retry accounting', function (): void {
    [$workflow] = createDefinition()
        ->addJob($job = (new RetryableJob())->onConnection('sync'))
        ->build();

    $workflow->markJobAsFailed($job, new RuntimeException('Original failure.'));
    $step = $workflow->jobs()->sole();

    $step->start();

    expect($step->fresh()->isFinished())->toBeTrue();
    expect($workflow->fresh()->jobs_failed)->toBe(0);
    expect($workflow->fresh()->jobs_processed)->toBe(1);
});
