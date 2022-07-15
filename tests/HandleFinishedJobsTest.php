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

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Sassnowski\Venture\Actions\HandleFinishedJobs;
use Sassnowski\Venture\Events\WorkflowFinished;
use Sassnowski\Venture\State\FakeWorkflowJobState;
use Sassnowski\Venture\State\FakeWorkflowState;
use Sassnowski\Venture\State\WorkflowStateStore;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\ThenCallback;

uses(TestCase::class);

beforeEach(function (): void {
    Bus::fake();

    WorkflowStateStore::fake();

    $this->action = new HandleFinishedJobs();
    $_SERVER['__then.count'] = 0;
});

it('transitions the state of all dependant jobs of the finished job', function (): void {
    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addGatedJob(new TestJob2(), [TestJob1::class])
        ->addGatedJob(new TestJob3(), [TestJob1::class])
        ->build();

    ($this->action)($job1);

    expect(WorkflowStateStore::forJob(TestJob2::class))
        ->transitioned->toBeTrue();
    expect(WorkflowStateStore::forJob(TestJob3::class))
        ->transitioned->toBeTrue();
});

it('runs a finished job\'s dependency if no other dependencies exist', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob2::class => new FakeWorkflowJobState(canRun: true),
        TestJob3::class => new FakeWorkflowJobState(canRun: true),
    ]);

    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addJob(new TestJob3(), [TestJob1::class])
        ->build();

    ($this->action)($job1);

    Bus::assertDispatched(TestJob2::class);
    Bus::assertDispatched(TestJob3::class);
});

it('does not run dependent jobs if they are not ready to run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob2::class => new FakeWorkflowJobState(canRun: false),
        TestJob3::class => new FakeWorkflowJobState(canRun: false),
    ]);

    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addJob(new TestJob3(), [TestJob1::class])
        ->build();

    ($this->action)($job1);

    Bus::assertNotDispatched(TestJob2::class);
    Bus::assertNotDispatched(TestJob3::class);
});

it('does not run jobs that are not dependent on the finished job, even if they could be run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob3::class => new FakeWorkflowJobState(canRun: true),
    ]);

    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), [TestJob2::class])
        ->build();

    ($this->action)($job1);

    Bus::assertNotDispatched(TestJob3::class);
});

it('marks the workflow as finished if all jobs have been processed', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->build();
    WorkflowStateStore::setupWorkflow(
        $workflow,
        new FakeWorkflowState(allJobsFinished: true),
    );

    ($this->action)($job);

    expect($workflow)->isFinished()->toBeTrue();
});

it('does not mark the workflow as finished if not all jobs have been processed', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->build();
    WorkflowStateStore::setupWorkflow(
        $workflow,
        new FakeWorkflowState(allJobsFinished: false),
    );

    ($this->action)($job);

    expect($workflow)->isFinished()->toBeFalse();
});

it('marks the corresponding job step finished whenever a job finishes', function (): void {
    [$workflow, $initalJobs] = createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob($job2 = new TestJob2())
        ->build();
    $state = WorkflowStateStore::forWorkflow($workflow);

    ($this->action)($job1);
    expect($state->finishedJobs)->toHaveKey(TestJob1::class);
    expect($state->finishedJobs)->not()->toHaveKey(TestJob2::class);

    ($this->action)($job2);
    expect($state->finishedJobs)->toHaveKey(TestJob2::class);
});

it('will not run any further jobs if the workflow has been cancelled', function (): void {
    Bus::fake();
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->build();

    $workflow->cancel();
    ($this->action)($job);

    Bus::assertNotDispatched(TestJob2::class);
});

it('runs the "then" callback after every job has been processed', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->then(function (): void {
            ++$_SERVER['__then.count'];
        })
        ->build();
    WorkflowStateStore::setupWorkflow(
        $workflow,
        new FakeWorkflowState(allJobsFinished: true),
    );

    ($this->action)($job);

    expect($_SERVER['__then.count'])->toBe(1);
});

it('supports invokable classes as then callbacks', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->then(new ThenCallback())
        ->build();
    WorkflowStateStore::setupWorkflow(
        $workflow,
        new FakeWorkflowState(allJobsFinished: true),
    );

    ($this->action)($job);

    expect($_SERVER['__then.count'])->toBe(1);
});

it('does not call the then callback if there are still pending jobs', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->then(new ThenCallback())
        ->build();
    WorkflowStateStore::setupWorkflow(
        $workflow,
        new FakeWorkflowState(allJobsFinished: false),
    );

    ($this->action)($job);

    expect($_SERVER['__then.count'])->toBe(0);
});

it('fires an event after a workflow has finished', function (): void {
    Event::fake([WorkflowFinished::class]);
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->build();
    WorkflowStateStore::setupWorkflow(
        $workflow,
        new FakeWorkflowState(allJobsFinished: true),
    );

    ($this->action)($job);

    Event::assertDispatched(
        WorkflowFinished::class,
        fn (WorkflowFinished $event): bool => $event->workflow->is($workflow),
    );
});
