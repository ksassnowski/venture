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
use Illuminate\Support\Facades\Event;
use Sassnowski\Venture\Actions\HandleFinishedJobs;
use Sassnowski\Venture\Dispatcher\FakeDispatcher;
use Sassnowski\Venture\Events\WorkflowFinished;
use Sassnowski\Venture\State\FakeWorkflowState;
use Sassnowski\Venture\State\WorkflowStateStore;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\ThenCallback;

uses(TestCase::class);

beforeEach(function (): void {
    Bus::fake();

    WorkflowStateStore::fake();

    $this->dispatcher = new FakeDispatcher();
    $this->action = new HandleFinishedJobs($this->dispatcher);
    $_SERVER['__then.count'] = 0;
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

it('dispatches all dependent jobs of the finished job', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->build();

    ($this->action)($job);

    $this->dispatcher->assertDependentJobsDispatchedFor(TestJob1::class);
});

it('does not dispatch the dependent jobs if the workflow has been cancelled', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->build();

    $workflow->cancel();
    ($this->action)($job);

    $this->dispatcher->assertDependentJobsNotDispatchedFor(TestJob1::class);
});

it('does not dispatch the dependent jobs if all jobs of the worklflow have finished', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->build();
    WorkflowStateStore::setupWorkflow($workflow, new FakeWorkflowState(allJobsFinished: true));

    ($this->action)($job);

    $this->dispatcher->assertDependentJobsNotDispatchedFor(TestJob1::class);
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
