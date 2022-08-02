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
use Sassnowski\Venture\Dispatcher\FakeDispatcher;
use Sassnowski\Venture\Dispatcher\JobDispatcherInterface;
use Sassnowski\Venture\WorkflowStep;
use Sassnowski\Venture\WorkflowStepAdapter;
use Stubs\LegacyJobWithInvokeMethod;
use Stubs\LegacyWorkflowJob;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;

uses(TestCase::class);

it('returns the original object if it already implements WorkflowStepInterface', function (): void {
    $job = new TestJob1();

    $result = WorkflowStepAdapter::fromJob($job);

    expect($result)->toBe($job);
});

it('returns a wrapped object if it uses the WorkflowStep trait but does not implement the interface', function (): void {
    $job = new LegacyWorkflowJob();

    $result = WorkflowStepAdapter::fromJob($job);

    expect($result)->toBeInstanceOf(WorkflowStepAdapter::class);
});

it('throws an exception if the object does not implement the interface or use the trait', function (): void {
    WorkflowStepAdapter::fromJob(new NonWorkflowJob());
})->throws(InvalidArgumentException::class);

it('proxies all interface methods to the underlying object', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    $adapter->withName('::name::');
    expect($adapter)->getName()->toBe('::name::');
    expect($job)->name->toBe('::name::');

    $adapter->withJobId('::job-id::');
    expect($adapter)->getJobId()->toBe('::job-id::');
    expect($job)->jobId->toBe('::job-id::');

    $adapter->withDelay(500);
    expect($adapter)->getDelay()->toBe(500);
    expect($job)->delay->toBe(500);

    $workflow = createWorkflow();
    $workflowJob = createWorkflowJob($workflow);
    $adapter->withStepId(Uuid::fromString($workflowJob->uuid));
    expect($adapter)->getStepId()->toBe($workflowJob->uuid);
    expect($job)->stepId->toBe($workflowJob->uuid);

    expect($adapter->step()->is($workflowJob))->toBeTrue();

    expect($adapter->workflow())->toBeNull();

    $adapter->withWorkflowId($workflow->id);
    expect($job)->workflowId->toEqual($workflow->id);
    expect($adapter->workflow()->is($workflow))->toBeTrue();

    $adapter->withDependantJobs([
        (new TestJob2())->withStepId($stepId1 = Str::orderedUuid()),
        (new TestJob3())->withStepId($stepId2 = Str::orderedUuid()),
    ]);
    expect($adapter)->getDependantJobs()->toEqual([$stepId1->toString(), $stepId2->toString()]);
    expect($job)->dependantJobs->toEqual([$stepId1->toString(), $stepId2->toString()]);

    $adapter->withDependencies([TestJob2::class, TestJob3::class]);
    expect($adapter)->getDependencies()->toEqual([TestJob2::class, TestJob3::class]);
    expect($job)->dependencies->toEqual([TestJob2::class, TestJob3::class]);

    $adapter->onConnection('::connection::');
    expect($adapter)->getConnection()->toBe('::connection::');
    expect($job)->connection->toBe('::connection::');

    $adapter->withGate();
    expect($adapter)->isGated()->toBeTrue();
    expect($job)->gated->toBeTrue();
});

it('returns the name of the wrapped job', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    expect($adapter->displayName())->toBe(LegacyWorkflowJob::class);
});

it('proxies all property accesses to the underlying object', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    expect($adapter)->foo->toBe('bar');
});

it('returns null if the property does not exist on the underlying object', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    expect($adapter)->bla->toBeNull();
});

it('proxies all unknown method calls to the underlying object', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    expect($adapter)->baz()->toBe('qux');
});

it('proxies all property writes to the underlying object', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    $adapter->foo = '::value::';

    expect($job)->foo->toBe('::value::');
});

it('can return the wrapped job', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    expect($adapter->unwrap())->toBe($job);
});

it('runs the underlying job\'s handle method if it exists', function (): void {
    $job = new LegacyWorkflowJob();
    $adapter = WorkflowStepAdapter::fromJob($job);

    $adapter->handle();

    expect($job)->handled->toBeTrue();
});

it('runs the underlying job\'s __invoke method if no handle method exists', function (): void {
    $job = new LegacyJobWithInvokeMethod();
    $adapter = WorkflowStepAdapter::fromJob($job);

    $adapter->handle();

    expect($job)->handled->toBeTrue();
});

it('resolves the underlying job\'s dependencies', function (): void {
    $dispatcher = new FakeDispatcher();
    app()->instance(JobDispatcherInterface::class, $dispatcher);

    $job = new class() {
        use WorkflowStep;

        public ?JobDispatcherInterface $dispatcher = null;

        public function handle(JobDispatcherInterface $dispatcher): void
        {
            $this->dispatcher = $dispatcher;
        }
    };
    $adapter = WorkflowStepAdapter::fromJob($job);

    $adapter->handle();

    expect($job->dispatcher)->toBe($dispatcher);
});
