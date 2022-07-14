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

use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\State\FakeWorkflowJobState;
use Stubs\TestJob1;
use Stubs\TestJob2;

uses(TestCase::class);

beforeEach(function (): void {
    $this->state = new FakeWorkflowJobState(new WorkflowJob());
});

afterEach(function (): void {
    FakeWorkflowJobState::restore();
});

it('is pending by default', function (): void {
    expect($this->state)->isPending()->toBeTrue();
});

it('is not finished by default', function (): void {
    expect($this->state)->hasFinished()->toBeFalse();
});

it('can be marked as finished', function (): void {
    $this->state->markAsFinished();

    expect($this->state)
        ->hasFinished()->toBeTrue()
        ->hasFailed()->toBeFalse()
        ->isPending()->toBeFalse()
        ->isProcessing()->toBeFalse()
        ->isGated()->toBeFalse();
});

it('is has not failed by default', function (): void {
    expect($this->state)->hasFailed()->toBeFalse();
});

it('can be marked as failed', function (): void {
    $exception = new Exception();

    $this->state->markAsFailed($exception);

    expect($this->state)
        ->hasFailed()->toBeTrue()
        ->exception->toBe($exception)
        ->hasFinished()->toBeFalse()
        ->isPending()->toBeFalse()
        ->isProcessing()->toBeFalse()
        ->isGated()->toBeFalse();
});

it('can not be run by default', function (): void {
    expect($this->state)->canRun()->toBeFalse();
});

it('is not gated by default', function (): void {
    expect($this->state)->isGated()->toBeFalse();
});

it('can be marked as gated', function (): void {
    $this->state->markAsGated();

    expect($this->state)
        ->isGated()->toBeTrue()
        ->hasFinished()->toBeFalse()
        ->hasFailed()->toBeFalse()
        ->isPending()->toBeFalse()
        ->isProcessing()->toBeFalse();
});

it('is not processing by default', function (): void {
    expect($this->state)->isProcessing()->toBeFalse();
});

it('can be marked as processing', function (): void {
    $this->state->markAsProcessing();

    expect($this->state)
        ->isProcessing()->toBeTrue()
        ->isGated()->toBeFalse()
        ->isPending()->toBeFalse()
        ->hasFinished()->toBeFalse()
        ->hasFailed()->toBeFalse();
});

it('can fake a jobs state based on the job ID', function (): void {
    FakeWorkflowJobState::setup([
        TestJob1::class => function (FakeWorkflowJobState $state): void {
            $state->canRun = true;
        },
        '::job-id::' => function (FakeWorkflowJobState $state): void {
            $state->markAsFinished();
        },
    ]);

    $job = new WorkflowJob([
        'job' => \serialize(new TestJob1()),
    ]);
    expect($job)->canRun()->toBeTrue();

    $job = new WorkflowJob([
        'job' => \serialize((new TestJob1())->withJobId('::job-id::')),
    ]);
    expect($job)->canRun()->toBeFalse();
    expect($job)->hasFinished()->toBeTrue();

    $job = new WorkflowJob([
        'job' => \serialize((new TestJob2())),
    ]);
    expect($job)->canRun()->toBeFalse();
    expect($job)->hasFinished()->toBeFalse();
});
