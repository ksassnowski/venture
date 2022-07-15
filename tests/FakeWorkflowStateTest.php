<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use Sassnowski\Venture\State\FakeWorkflowState;
use Stubs\TestJob1;

beforeEach(function (): void {
    $this->state = new FakeWorkflowState();
});

it('has no finished jobs by default', function (): void {
    expect($this->state)->finishedJobs->toBeEmpty();
});

it('can mark a job as finished', function (): void {
    $this->state->markJobAsFinished(new TestJob1());

    expect($this->state)->finishedJobs->toHaveKey(TestJob1::class);
});

it('has no failed jobs by default', function (): void {
    expect($this->state)->failedJobs->toBeEmpty();
});

it('can mark a job as failed', function (): void {
    $exception = new Exception();

    $this->state->markJobAsFailed(new TestJob1(), $exception);

    expect($this->state)
        ->failedJobs->toHaveKey(TestJob1::class);
    expect($this->state->failedJobs[TestJob1::class])
        ->toBe($exception);
});

it('is not finished by default', function (): void {
    expect($this->state)->isFinished()->toBeFalse();
});

it('can be marked as finished', function (): void {
    $this->state->markAsFinished();

    expect($this->state)->isFinished()->toBeTrue();
});

it('is not cancelled by default', function (): void {
    expect($this->state)->isCancelled()->toBeFalse();
});

it('can be marked as cancelled', function (): void {
    $this->state->markAsCancelled();

    expect($this->state)->isCancelled()->toBeTrue();
});

it('can retrieve remaining jobs', function (): void {
    expect($this->state)->remainingJobs()->toBe(0);

    $this->state->remainingJobs = 5;

    expect($this->state)->remainingJobs()->toBe(5);
});

it('can check whether it has run', function (): void {
    expect($this->state)->hasRan()->toBeFalse();

    $this->state->hasRan = true;

    expect($this->state)->hasRan()->toBeTrue();
});
