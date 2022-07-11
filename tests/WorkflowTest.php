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

use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;
use Sassnowski\Venture\Events\JobCreated;
use Sassnowski\Venture\Events\JobCreating;
use Stubs\NestedWorkflow;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\WorkflowWithWorkflow;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__then.count'] = 0;
    $_SERVER['__catch.count'] = 0;
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('it increments the finished jobs count when a job finished', function (): void {
    $job1 = new TestJob1();
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
    ]);

    $workflow->onStepFinished($job1);

    assertEquals(1, $workflow->refresh()->jobs_processed);
});

it('stores a finished job\'s id', function ($job, string $expectedJobId): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
    ]);

    $workflow->onStepFinished($job);

    assertEquals([$expectedJobId], $workflow->refresh()->finished_jobs);
})->with([
    'no job id should default to class name' => [
        new TestJob1(),
        TestJob1::class,
    ],
    'use existing job id' => [
        (new TestJob1())->withJobId('::job-id::'),
        '::job-id::',
    ],
]);

it('it stores finished job id for nested workflow jobs', function (): void {
    $workflow = new WorkflowWithWorkflow(new NestedWorkflow(
        $job = new TestJob1(),
    ));
    $definition = $workflow->definition();
    [$model, $initial] = $definition->build();

    $model->onStepFinished($job);

    assertEquals([NestedWorkflow::class . '.' . TestJob1::class], $model->refresh()->finished_jobs);
});

it('marks itself as finished if the all jobs have been processed', function (): void {
    Carbon::setTestNow(now());

    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $workflow = createWorkflow([
        'job_count' => 2,
        'jobs_processed' => 0,
    ]);

    $workflow->onStepFinished($job1);
    assertNull($workflow->refresh()->finished_at);

    $workflow->onStepFinished($job2);
    assertEquals(now()->timestamp, $workflow->refresh()->finished_at->timestamp);
});

it('marks the corresponding job step finished whenever a job finishes', function (): void {
    Carbon::setTestNow(now());
    $job = new TestJob1();
    $uuid = Str::orderedUuid();
    $job->withStepId($uuid);

    $workflow = createWorkflow([
        'job_count' => 1,
    ]);
    $step = createWorkflowJob($workflow, [
        'uuid' => $uuid,
        'workflow_id' => $workflow->id,
    ]);

    $workflow->onStepFinished($job);

    assertEquals(now()->timestamp, $step->refresh()->finished_at->timestamp);
});

it('runs a finished job\'s dependency if no other dependencies exist', function (): void {
    Bus::fake();

    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class]);
    $workflow = createWorkflow([
        'job_count' => 2,
    ]);

    $workflow->addJobs(wrapJobsForWorkflow([$job1, $job2]));

    $workflow->onStepFinished($job1);

    Bus::assertDispatched(TestJob2::class);
});

it('does not run a dependant job if some of its dependencies have not finished yet', function (): void {
    Bus::fake();

    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());
    $job3 = (new TestJob3())->withStepId(Str::orderedUuid());
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class, TestJob3::class]);
    $job3->withDependantJobs([$job2]);
    $workflow = createWorkflow([
        'job_count' => 3,
    ]);

    $workflow->onStepFinished($job1);

    Bus::assertNotDispatched(TestJob2::class);
});

it('runs a job if all of its dependencies have finished', function (): void {
    Bus::fake();

    $workflow = createWorkflow([
        'job_count' => 3,
    ]);

    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());
    $job3 = (new TestJob3())->withJobId('::job-3-id::')->withStepId(Str::orderedUuid());
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class, '::job-3-id::']);
    $job3->withDependantJobs([$job2]);

    $workflow->addJobs(wrapJobsForWorkflow([$job1, $job2, $job3]));

    $workflow->onStepFinished($job1);
    $workflow->onStepFinished($job3);

    Bus::assertDispatched(TestJob2::class);
});

it('calculates its remaining jobs', function (): void {
    $workflow = createWorkflow([
        'job_count' => 3,
        'jobs_processed' => 2,
    ]);

    assertEquals(1, $workflow->remainingJobs());
});

it('runs the "then" callback after every job has been processed', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'then_callback' => \serialize(new SerializableClosure(function (): void {
            ++$_SERVER['__then.count'];
        })),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(1, $_SERVER['__then.count']);
});

it('supports invokable classes as then callbacks', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'then_callback' => \serialize(new ThenCallback()),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(1, $_SERVER['__then.count']);
});

it('does not call the then callback if there are still pending jobs', function (): void {
    $workflow = createWorkflow([
        'job_count' => 2,
        'then_callback' => \serialize(new ThenCallback()),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(0, $_SERVER['__then.count']);
});

it('does not break a leg if no then callback is configured', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'then_callback' => null,
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(0, $_SERVER['__then.count']);
});

it('can run the "catch" callback if it is configured', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'catch_callback' => \serialize(new SerializableClosure(function (): void {
            ++$_SERVER['__catch.count'];
        })),
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $_SERVER['__catch.count']);
});

it('supports invokable classes as catch callbacks', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'catch_callback' => \serialize(new CatchCallback()),
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $_SERVER['__catch.count']);
});

it('does not break a leg if no catch callback is configured', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'catch_callback' => null,
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(0, $_SERVER['__catch.count']);
});

it('has not run by default', function (): void {
    assertFalse(createWorkflow(['job_count' => 1])->hasRan());
});

it('has ran when job count equals total processed and failed', function (): void {
    $workflow = createWorkflow([
        'job_count' => 4,
        'jobs_processed' => 3,
        'jobs_failed' => 1,
    ]);

    assertTrue($workflow->hasRan());
});

it('has not ran when job count does not equal total processed and failed', function (): void {
    $workflow = createWorkflow([
        'job_count' => 4,
        'jobs_processed' => 2,
        'jobs_failed' => 1,
    ]);

    assertFalse($workflow->hasRan());
});

it('is not cancelled by default', function (): void {
    assertFalse(createWorkflow()->isCancelled());
});

it('can be cancelled', function (): void {
    Carbon::setTestNow(now());
    $workflow = createWorkflow();

    $workflow->cancel();

    assertTrue($workflow->isCancelled());
    assertEquals(now()->timestamp, $workflow->cancelled_at->timestamp);
});

it('cancelling an already cancelled job does not update timestamp', function (): void {
    Carbon::setTestNow(now());
    $workflow = createWorkflow();

    $workflow->cancel();
    $cancelledAt = $workflow->cancelled_at;

    Carbon::setTestNow(now()->addHour());
    $workflow->cancel();

    assertEquals($cancelledAt, $workflow->fresh()->cancelled_at);
});

it('will not run any further jobs if it has been cancelled', function (): void {
    Bus::fake();

    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class]);
    $workflow = createWorkflow([
        'job_count' => 2,
        'cancelled_at' => now(),
    ]);

    $workflow->onStepFinished($job1);

    Bus::assertNotDispatched(TestJob2::class);
});

it('increments the failed job count', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_failed' => 0,
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $workflow->jobs_failed);
});

it('marks a step as failed', function (): void {
    Carbon::setTestNow(now());

    $workflow = createWorkflow([
        'job_count' => 1,
    ]);
    $job = new TestJob1();
    $uuid = Str::orderedUuid();
    $job->withStepId($uuid);
    $step = createWorkflowJob($workflow, [
        'uuid' => $uuid,
        'failed_at' => null,
    ]);
    $exception = new Exception();

    $workflow->onStepFailed($job, $exception);

    $step->refresh();
    assertEquals(now()->timestamp, $step->failed_at->timestamp);
    assertEquals((string) $exception, $step->exception);
});

it('can fetch all of its failed jobs', function (): void {
    $workflow = createWorkflow();
    $failedJob1 = createWorkflowJob($workflow, ['failed_at' => now()]);
    $failedJob2 = createWorkflowJob($workflow, ['failed_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, ['failed_at' => null]);

    $actual = $workflow->failedJobs();

    assertCount(2, $actual);
    assertTrue($actual->contains($failedJob1));
    assertTrue($actual->contains($failedJob2));
});

it('can fetch all of its pending jobs', function (): void {
    $workflow = createWorkflow();
    $failedJob = createWorkflowJob($workflow, ['failed_at' => now()]);
    $finishedJob = createWorkflowJob($workflow, ['finished_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, [
        'failed_at' => null,
        'finished_at' => null,
    ]);

    $actual = $workflow->pendingJobs();

    assertCount(1, $actual);
    assertTrue($actual[0]->is($pendingJob));
});

it('can fetch all of its finished jobs', function (): void {
    $workflow = createWorkflow();
    $failedJob = createWorkflowJob($workflow, ['failed_at' => now()]);
    $finishedJob = createWorkflowJob($workflow, ['finished_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, [
        'failed_at' => null,
        'finished_at' => null,
    ]);

    $actual = $workflow->finishedJobs();

    assertCount(1, $actual);
    assertTrue($actual[0]->is($finishedJob));
});

it('returns the dependency graph as an adjacency list', function (): void {
    Carbon::setTestNow('2020-11-11 11:11:00');
    $exception = new Exception();

    $workflow = createWorkflow();
    $job3 = createWorkflowJob($workflow, [
        'name' => '::job-3-name::',
    ]);
    $job2 = createWorkflowJob($workflow, [
        'name' => '::job-2-name::',
        'failed_at' => now()->timestamp,
        'exception' => (string) $exception,
        'edges' => [$job3->uuid],
    ]);
    $job1 = createWorkflowJob($workflow, [
        'name' => '::job-1-name::',
        'edges' => [
            $job2->uuid,
            $job3->uuid,
        ],
    ]);

    $adjacencyList = $workflow->asAdjacencyList();

    assertEquals([
        $job1->uuid => [
            'name' => '::job-1-name::',
            'finished_at' => null,
            'failed_at' => null,
            'exception' => null,
            'edges' => [
                $job2->uuid,
                $job3->uuid,
            ],
        ],
        $job2->uuid => [
            'name' => '::job-2-name::',
            'finished_at' => null,
            'failed_at' => now(),
            'exception' => (string) $exception,
            'edges' => [
                $job3->uuid,
            ],
        ],
        $job3->uuid => [
            'name' => '::job-3-name::',
            'finished_at' => null,
            'exception' => null,
            'failed_at' => null,
            'edges' => [],
        ],
    ], $adjacencyList);
});

it('fires an event for each job that gets added', function (): void {
    Event::fake([JobCreating::class]);
    $workflow = createWorkflow();
    $job1 = [
        'job' => (new TestJob1())->withStepId(Str::orderedUuid()),
        'name' => '::name-1::',
    ];
    $job2 = [
        'job' => (new TestJob2())->withStepId(Str::orderedUuid()),
        'name' => '::name-2::',
    ];

    $workflow->addJobs([$job1, $job2]);

    Event::assertDispatched(JobCreating::class, 2);
});

it('fires an event for each job that was created', function (): void {
    Event::fake([JobCreated::class]);
    $workflow = createWorkflow();
    $job1 = [
        'job' => (new TestJob1())->withStepId(Str::orderedUuid()),
        'name' => '::name-1::',
    ];
    $job2 = [
        'job' => (new TestJob2())->withStepId(Str::orderedUuid()),
        'name' => '::name-2::',
    ];

    $workflow->addJobs([$job1, $job2]);

    Event::assertDispatched(JobCreated::class, 2);
});

class ThenCallback
{
    public function __invoke(): void
    {
        ++$_SERVER['__then.count'];
    }
}

class CatchCallback
{
    public function __invoke(): void
    {
        ++$_SERVER['__catch.count'];
    }
}
