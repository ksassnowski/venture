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

use Illuminate\Support\Facades\Bus;
use Sassnowski\Venture\Exceptions\CannotRetryJobException;
use Sassnowski\Venture\Exceptions\JobAlreadyStartedException;
use Stubs\TestJob1;

uses(TestCase::class);

test('a stale step cannot dispatch a job that has already started', function (bool $finished): void {
    Bus::fake();

    [$workflow] = createDefinition()->addJob(new TestJob1())->build();
    $first = $workflow->jobs()->sole();
    $second = $workflow->jobs()->sole();

    $first->start();

    if ($finished) {
        $workflow->markJobAsFinished($first->step());
    }

    expect(fn () => $second->start())->toThrow(JobAlreadyStartedException::class);
    expect($first->fresh()->isFinished())->toBe($finished);
    Bus::assertDispatchedTimes(TestJob1::class, 1);
})->with([
    'queued' => [false],
    'finished' => [true],
]);

test('an enqueue failure restores the step state and failure count', function (bool $retry): void {
    [$workflow] = createDefinition()->addJob($job = new TestJob1())->build();

    if ($retry) {
        $workflow->markJobAsFailed($job, new RuntimeException('Original failure.'));
    }

    $step = $workflow->jobs()->sole();
    $attributes = $step->only(['started_at', 'failed_at', 'exception']);
    $method = $retry ? 'retry' : 'start';
    $dispatcher = Bus::getFacadeRoot();

    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable.'));

    expect(fn () => $step->{$method}())->toThrow(RuntimeException::class, 'Queue unavailable.');
    expect($step->fresh()->only(['started_at', 'failed_at', 'exception']))->toEqual($attributes);
    expect($workflow->fresh()->jobs_failed)->toBe($retry ? 1 : 0);

    Bus::swap($dispatcher);
    Bus::fake();

    $step->{$method}();

    expect($step->fresh()->isProcessing())->toBeTrue();
    expect($workflow->fresh()->jobs_failed)->toBe(0);
    Bus::assertDispatchedTimes(TestJob1::class, 1);
})->with([
    'initial dispatch' => [false],
    'retry' => [true],
]);

test('a failed step requires an explicit retry and cannot queue duplicate retries', function (): void {
    Bus::fake();

    [$workflow] = createDefinition()->addJob($job = new TestJob1())->build();
    $workflow->markJobAsFailed($job, new RuntimeException('Original failure.'));
    $first = $workflow->jobs()->sole();
    $second = $workflow->jobs()->sole();

    expect(fn () => $first->start())->toThrow(JobAlreadyStartedException::class);

    $first->retry();

    expect(fn () => $second->retry())->toThrow(CannotRetryJobException::class);
    expect($first->fresh()->isProcessing())->toBeTrue();
    expect($workflow->fresh()->jobs_failed)->toBe(0);
    Bus::assertDispatchedTimes(TestJob1::class, 1);
});
