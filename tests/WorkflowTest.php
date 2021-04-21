<?php declare(strict_types=1);

use Carbon\Carbon;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Opis\Closure\SerializableClosure;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertEquals;

uses(TestCase::class);

beforeEach(function () {
    $_SERVER['__then.count'] = 0;
    $_SERVER['__catch.count'] = 0;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('it increments the finished jobs count when a job finished', function () {
    $job1 = new TestJob1();
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
    ]);

    $workflow->onStepFinished($job1);

    assertEquals(1, $workflow->refresh()->jobs_processed);
});

it('marks itself as finished if the all jobs have been processed', function () {
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

it('marks the corresponding job step finished whenever a job finishes', function () {
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

it('runs a finished job\'s dependency if no other dependencies exist', function () {
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

it('does not run a dependant job if some of its dependencies have not finished yet', function () {
    Bus::fake();

    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new TestJob3();
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class, TestJob3::class]);
    $job3->withDependantJobs([$job2]);
    $workflow = createWorkflow([
        'job_count' => 3,
    ]);

    $workflow->onStepFinished($job1);

    Bus::assertNotDispatched(TestJob2::class);
});

it('runs a job if all of its dependencies have finished', function () {
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

it('calculates its remaining jobs', function () {
    $workflow = createWorkflow([
        'job_count' => 3,
        'jobs_processed' => 2,
    ]);

    assertEquals(1, $workflow->remainingJobs());
});

it('runs the "then" callback after every job has been processed', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'then_callback' => serialize(SerializableClosure::from(function () {
            $_SERVER['__then.count']++;
        }))
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(1, $_SERVER['__then.count']);
});

it('supports invokable classes as then callbacks', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'then_callback' => serialize(new ThenCallback()),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(1, $_SERVER['__then.count']);
});

it('does not call the then callback if there are still pending jobs', function () {
    $workflow = createWorkflow([
        'job_count' => 2,
        'then_callback' => serialize(new ThenCallback()),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(0, $_SERVER['__then.count']);
});

it('does not break a leg if no then callback is configured', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'then_callback' => null,
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(0, $_SERVER['__then.count']);
});

it('can run the "catch" callback if it is configured', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'catch_callback' => serialize(SerializableClosure::from(function () {
            $_SERVER['__catch.count']++;
        }))
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $_SERVER['__catch.count']);
});

it('supports invokable classes as catch callbacks', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'catch_callback' => serialize(new CatchCallback()),
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $_SERVER['__catch.count']);
});

it('does not break a leg if no catch callback is configured', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'catch_callback' => null,
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(0, $_SERVER['__catch.count']);
});

it('is not cancelled by default', function () {
    assertFalse(createWorkflow()->isCancelled());
});

it('can be cancelled', function () {
    Carbon::setTestNow(now());
    $workflow = createWorkflow();

    $workflow->cancel();

    assertTrue($workflow->isCancelled());
    assertEquals(now()->timestamp, $workflow->cancelled_at->timestamp);
});

it('cancelling an already cancelled job does not update timestamp', function () {
    Carbon::setTestNow(now());
    $workflow = createWorkflow();

    $workflow->cancel();
    $cancelledAt = $workflow->cancelled_at;

    Carbon::setTestNow(now()->addHour());
    $workflow->cancel();

    assertEquals($cancelledAt, $workflow->fresh()->cancelled_at);
});

it('will not run any further jobs if it has been cancelled', function () {
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

it('increments the failed job count', function () {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_failed' => 0,
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $workflow->jobs_failed);
});

it('marks a step as failed', function () {
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

it('can fetch all of its failed jobs', function () {
    $workflow = createWorkflow();
    $failedJob1 = createWorkflowJob($workflow, ['failed_at' => now()]);
    $failedJob2 = createWorkflowJob($workflow, ['failed_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, ['failed_at' => null]);

    $actual = $workflow->failedJobs();

    assertCount(2, $actual);
    assertTrue($actual->contains($failedJob1));
    assertTrue($actual->contains($failedJob2));
});

it('can fetch all of its pending jobs', function () {
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

it('can fetch all of its finished jobs', function () {
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

it('returns the dependency graph as an adjacency list', function () {
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
        'edges' => [$job3->uuid]
    ]);
    $job1 = createWorkflowJob($workflow, [
        'name' => '::job-1-name::',
        'edges' => [
            $job2->uuid,
            $job3->uuid
        ]
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
        ]
    ], $adjacencyList);
});

class ThenCallback
{
    public function __invoke()
    {
        $_SERVER['__then.count']++;
    }
}

class CatchCallback
{
    public function __invoke()
    {
        $_SERVER['__catch.count']++;
    }
}
