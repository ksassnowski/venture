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

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\FakeIDGenerator;
use Sassnowski\Venture\Listeners\ConfigureNewWorkflowJob;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestJob1;
use Stubs\TestWorkflow;

beforeEach(function (): void {
    $this->definition = new WorkflowDefinition(new TestWorkflow());
    $this->listener = new ConfigureNewWorkflowJob(
        new FakeIDGenerator('::fake-id::'),
    );
});

afterEach(function (): void {
    Str::createUuidsNormally();
});

it('sets the job name to the job\'s class name if no explicit name was provided', function (): void {
    $event = new JobAdding($this->definition, new TestJob1(), null, null, null);

    ($this->listener)($event);

    expect($event->job)->getName()->toBe(TestJob1::class);
});

it('sets the job name job name if an explicit name was provided', function (): void {
    $event = new JobAdding($this->definition, new TestJob1(), '::job-name::', null, null);

    ($this->listener)($event);

    expect($event->job)->getName()->toBe('::job-name::');
});

it('sets the job ID on the job', function (): void {
    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, null, null, '::original-job-id::');

    ($this->listener)($event);

    expect($job)->getJobId()->toBe('::original-job-id::');
});

it('generates a new job ID of no explicit ID was provided', function (): void {
    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, null, null, null);

    ($this->listener)($event);

    expect($job)->getJobId()->toBe('::fake-id::');
});

it('generates a step ID for the job', function (): void {
    Str::createUuidsUsing(fn () => Uuid::fromString('3de013b7-08bc-438b-8a1b-708c748da060'));

    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, null, null, null);

    ($this->listener)($event);

    expect($job)->getStepId()->toBe('3de013b7-08bc-438b-8a1b-708c748da060');
});

it('does not overwrite the job\'s step ID if it has already been set', function (): void {
    $job = new TestJob1();
    $stepID = Str::orderedUuid();
    $job->withStepId($stepID);
    $event = new JobAdding($this->definition, $job, null, null, null);

    ($this->listener)($event);

    expect($job)->getStepId()->toBe((string) $stepID);
});

it('sets the delay on the job', function (): void {
    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, null, 300, null);

    ($this->listener)($event);

    expect($job)->getDelay()->toBe(300);
});
