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
use Sassnowski\Venture\Exceptions\CannotRetryJobException;
use Sassnowski\Venture\Exceptions\JobNotReadyException;
use Sassnowski\Venture\State\FakeWorkflowJobState;
use Sassnowski\Venture\State\WorkflowStateStore;
use Stubs\TestJob1;

uses(TestCase::class);

beforeEach(function (): void {
    Bus::fake();
    WorkflowStateStore::fake();

    $this->workflow = createWorkflow();
});

it('starts a job that can run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob1::class => new FakeWorkflowJobState(canRun: true),
    ]);

    $job = createWorkflowJob(
        $this->workflow,
        ['job' => \serialize(new TestJob1())],
    );

    $job->start();

    Bus::assertDispatchedTimes(TestJob1::class, 1);
});

it('throws an exception when trying to start a job that cannot run', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob1::class => new FakeWorkflowJobState(canRun: false),
    ]);

    $job = createWorkflowJob(
        $this->workflow,
        ['job' => \serialize(new TestJob1())],
    );

    expect(fn () => $job->start())->toThrow(JobNotReadyException::class);

    Bus::assertNotDispatched(TestJob1::class);
});

it('marks the job as processing after starting', function (): void {
    WorkflowStateStore::setupJobs([
        TestJob1::class => new FakeWorkflowJobState(canRun: true),
    ]);

    $job = createWorkflowJob(
        $this->workflow,
        ['job' => \serialize(new TestJob1())],
    );

    $job->start();

    expect($job->fresh())->isProcessing()->toBeTrue();
});
