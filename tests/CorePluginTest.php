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

use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\FakeIDGenerator;
use Sassnowski\Venture\Plugin\Core;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestJob1;
use Stubs\TestWorkflow;
use Stubs\WorkflowWithJob;

uses(TestCase::class)->group('plugin');

beforeEach(function (): void {
    $this->plugin = new Core(new FakeIDGenerator('::fake-id::'));
    $this->definition = new WorkflowDefinition(new TestWorkflow());
});

afterEach(function (): void {
    Str::createUuidsNormally();
});

it('sets the job name to the job\'s class name if no explicit name was provided', function (): void {
    $event = new JobAdding($this->definition, new TestJob1(), [], '', null, '');

    $this->plugin->onJobAdding($event);

    expect($event)->name->toBe(TestJob1::class);
});

it('does not change the job name if an explicit name was provided', function (): void {
    $event = new JobAdding($this->definition, new TestJob1(), [], '::job-name::', null, '');

    $this->plugin->onJobAdding($event);

    expect($event)->name->toBe('::job-name::');
});

it('sets the job ID on the job', function (): void {
    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, [], '', null, '::original-job-id::');

    $this->plugin->onJobAdding($event);

    expect($job)->jobId->toBe('::original-job-id::');
});

it('generates a new job ID of no explicit ID was provided', function (): void {
    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, [], '', null, '');

    $this->plugin->onJobAdding($event);

    expect($job)->jobId->toBe('::fake-id::');
});

it('does not overwrite the job\'s job ID if it has already been set', function (): void {
    $job = new TestJob1();
    $job->withJobId('::original-job-id::');
    $event = new JobAdding($this->definition, $job, [], '', null, '');

    $this->plugin->onJobAdding($event);

    expect($job)->jobId->toBe('::original-job-id::');
});

it('generates a step ID for the job', function (): void {
    Str::createUuidsUsing(fn () => Uuid::fromString('3de013b7-08bc-438b-8a1b-708c748da060'));

    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, [], '', null, '');

    $this->plugin->onJobAdding($event);

    expect($job)->stepId->toBe('3de013b7-08bc-438b-8a1b-708c748da060');
});

it('does not overwrite the job\'s step ID if it has already been set', function (): void {
    $job = new TestJob1();
    $stepID = Str::orderedUuid();
    $job->withStepId($stepID);
    $event = new JobAdding($this->definition, $job, [], '', null, '');

    $this->plugin->onJobAdding($event);

    expect($job)->stepId->toBe((string) $stepID);
});

it('sets the delay on the job', function (): void {
    $job = new TestJob1();
    $event = new JobAdding($this->definition, $job, [], '', 300, '');

    $this->plugin->onJobAdding($event);

    expect($job)->delay->toBe(300);
});

it('generates an ID for a nested workflow if no explicit ID was provided', function (): void {
    $definition = createDefinition();
    $workflow = new WorkflowWithJob();
    $event = new WorkflowAdding($definition, $workflow->definition(), [], '');

    $this->plugin->onWorkflowAdding($event);

    expect($event->workflowID)->toBe('::fake-id::');
});

it('does not overwrite the workflow ID if it was provided', function (): void {
    $definition = createDefinition();
    $workflow = new WorkflowWithJob();
    $event = new WorkflowAdding($definition, $workflow->definition(), [], '::workflow-id::');

    $this->plugin->onWorkflowAdding($event);

    expect($event->workflowID)->toBe('::workflow-id::');
});

it('namespaces the nested workflow\'s job ids', function (): void {
    $definition = createDefinition();
    $nestedDefinition = (new WorkflowWithJob())->definition();
    $event = new WorkflowAdding($definition, $nestedDefinition, [], '::workflow-id::');

    $this->plugin->onWorkflowAdding($event);

    foreach ($nestedDefinition->jobs() as $jobID => $job) {
        expect($job['job']->jobId)->toBe('::workflow-id::.' . $jobID);
    }
});
