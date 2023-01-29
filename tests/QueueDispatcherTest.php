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
use Illuminate\Support\Str;
use Sassnowski\Venture\Dispatcher\QueueDispatcher;
use Sassnowski\Venture\State\FakeWorkflowJobState;
use Sassnowski\Venture\State\WorkflowStateStore;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;

uses(TestCase::class);

beforeEach(function (): void {
    Bus::fake();
    WorkflowStateStore::fake();
});

it('transitions the state of all jobs', function (): void {
    $workflow = createWorkflow();
    createWorkflowJob($workflow, [
        'uuid' => $stepId1 = Str::orderedUuid(),
        'job' => \serialize($job1 = (new TestJob1())->withStepId($stepId1)),
    ]);
    createWorkflowJob($workflow, [
        'uuid' => $stepId2 = Str::orderedUuid(),
        'job' => \serialize($job2 = (new TestJob2())->withStepId($stepId2)),
    ]);

    (new QueueDispatcher())->dispatch([$job1, $job2]);

    expect(WorkflowStateStore::forJob(TestJob1::class))
        ->transitioned->toBeTrue();
    expect(WorkflowStateStore::forJob(TestJob2::class))
        ->transitioned->toBeTrue();
});

it('dispatches all jobs that can be run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob1::class => new FakeWorkflowJobState(canRun: true),
        TestJob2::class => new FakeWorkflowJobState(canRun: true),
    ]);

    $workflow = createWorkflow();
    createWorkflowJob($workflow, [
        'uuid' => $stepId1 = Str::orderedUuid(),
        'job' => \serialize($job1 = (new TestJob1())->withStepId($stepId1)),
    ]);
    createWorkflowJob($workflow, [
        'uuid' => $stepId2 = Str::orderedUuid(),
        'job' => \serialize($job2 = (new TestJob2())->withStepId($stepId2)),
    ]);

    (new QueueDispatcher())->dispatch([$job1, $job2]);

    Bus::assertDispatched(TestJob1::class);
    Bus::assertDispatched(TestJob2::class);
});

it('does not dispatch jobs that cannot be run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob1::class => new FakeWorkflowJobState(canRun: false),
        TestJob2::class => new FakeWorkflowJobState(canRun: false),
    ]);

    $workflow = createWorkflow();
    createWorkflowJob($workflow, [
        'uuid' => $stepId1 = Str::orderedUuid(),
        'job' => \serialize($job1 = (new TestJob1())->withStepId($stepId1)),
    ]);
    createWorkflowJob($workflow, [
        'uuid' => $stepId2 = Str::orderedUuid(),
        'job' => \serialize($job2 = (new TestJob2())->withStepId($stepId2)),
    ]);

    (new QueueDispatcher())->dispatch([$job1, $job2]);

    Bus::assertNotDispatched(TestJob1::class);
    Bus::assertNotDispatched(TestJob2::class);
});

it('transitions the state of all dependant jobs of the job', function (): void {
    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addGatedJob(new TestJob3(), [TestJob1::class])
        ->build();

    (new QueueDispatcher())->dispatchDependentJobs($job1);

    expect(WorkflowStateStore::forJob(TestJob2::class))
        ->transitioned->toBeTrue();
    expect(WorkflowStateStore::forJob(TestJob3::class))
        ->transitioned->toBeTrue();
});

it('does not transition the state of jobs that are not dependent on the job', function (): void {
    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), [TestJob2::class])
        ->build();

    (new QueueDispatcher())->dispatchDependentJobs($job1);

    expect(WorkflowStateStore::forJob(TestJob2::class))
        ->transitioned->toBeFalse();
    expect(WorkflowStateStore::forJob(TestJob3::class))
        ->transitioned->toBeFalse();
});

it('runs all dependent jobs of the job that can run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob2::class => new FakeWorkflowJobState(canRun: true),
        TestJob3::class => new FakeWorkflowJobState(canRun: true),
    ]);

    createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addJob(new TestJob3(), [TestJob1::class])
        ->build();

    (new QueueDispatcher())->dispatchDependentJobs($job1);

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

    (new QueueDispatcher())->dispatchDependentJobs($job1);

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

    (new QueueDispatcher())->dispatchDependentJobs($job1);

    Bus::assertNotDispatched(TestJob3::class);
});
