<?php declare(strict_types=1);

use Carbon\Carbon;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Illuminate\Support\Str;
use Sassnowski\Venture\Workflow;
use Illuminate\Support\Facades\Bus;
use Sassnowski\Venture\WorkflowJob;
use Opis\Closure\SerializableClosure;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertEquals;

uses(TestCase::class);

beforeEach(function () {
    $_SERVER['__then.count'] = 0;
    $_SERVER['__catch.count'] = 0;
});

test('starting a workflow dispatches the initial batch', function () {
    Bus::fake();

    (new Workflow())->start([new TestJob1(), new TestJob2()]);

    Bus::assertDispatched(TestJob1::class);
    Bus::assertDispatched(TestJob2::class);
});

it('it increments the finished jobs count when a job finished', function () {
    $job1 = new TestJob1();
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);

    $workflow->onStepFinished($job1);

    assertEquals(1, $workflow->refresh()->jobs_processed);
});

it('marks itself as finished if the all jobs have been processed', function () {
    Carbon::setTestNow(now());

    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $workflow = Workflow::create([
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);

    $workflow->onStepFinished($job1);
    assertNull($workflow->refresh()->finished_at);

    $workflow->onStepFinished($job2);
    assertEquals(now(), $workflow->refresh()->finished_at);
});

it('marks the corresponding job step finished whenever a job finishes', function () {
    Carbon::setTestNow(now());
    $job = new TestJob1();
    $uuid = Str::orderedUuid();
    $job->withStepId($uuid);

    $workflow = Workflow::create([
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $step = WorkflowJob::create([
        'uuid' => $uuid,
        'name' => '::name::',
        'job' => '::job::',
        'workflow_id' => $workflow->id,
    ]);

    $workflow->onStepFinished($job);

    assertEquals(now(), $step->refresh()->finished_at);
});

it('runs a finished job\'s dependency if no other dependencies exist', function () {
    Bus::fake();

    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class]);
    $workflow = Workflow::create([
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);

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
    $workflow = Workflow::create([
        'job_count' => 3,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);

    $workflow->onStepFinished($job1);

    Bus::assertNotDispatched(TestJob2::class);
});

it('runs a job if all of its dependencies have finished', function () {
    Bus::fake();

    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new TestJob3();
    $job1->withDependantJobs([$job2]);
    $job2->withDependencies([TestJob1::class, TestJob3::class]);
    $job3->withDependantJobs([$job2]);
    $workflow = Workflow::create([
        'job_count' => 3,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);

    $workflow->onStepFinished($job1);
    $workflow->onStepFinished($job3);

    Bus::assertDispatched(TestJob2::class);
});

it('calculates its remaining jobs', function () {
    $workflow = Workflow::create([
        'job_count' => 3,
        'jobs_processed' => 2,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);

    assertEquals(1, $workflow->remainingJobs());
});

it('runs the "then" callback after every job has been processed', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'then_callback' => serialize(SerializableClosure::from(function () {
            $_SERVER['__then.count']++;
        }))
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(1, $_SERVER['__then.count']);
});

it('supports invokable classes as then callbacks', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'then_callback' => serialize(new ThenCallback()),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(1, $_SERVER['__then.count']);
});

it('does not call the then callback if there are still pending jobs', function () {
    $workflow = Workflow::create([
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'then_callback' => serialize(new ThenCallback()),
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(0, $_SERVER['__then.count']);
});

it('does not break a leg if no then callback is configured', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'then_callback' => null,
    ]);

    $workflow->onStepFinished(new TestJob1());

    assertEquals(0, $_SERVER['__then.count']);
});

it('can run the "catch" callback if it is configured', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'then_callback' => null,
        'catch_callback' => serialize(SerializableClosure::from(function () {
            $_SERVER['__catch.count']++;
        }))
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $_SERVER['__catch.count']);
});

it('supports invokable classes as catch callbacks', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => serialize(new CatchCallback()),
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(1, $_SERVER['__catch.count']);
});

it('does not break a leg if no catch callback is configured', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'then_callback' => null,
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    assertEquals(0, $_SERVER['__catch.count']);
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
