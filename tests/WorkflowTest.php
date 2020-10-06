<?php declare(strict_types=1);

use Carbon\Carbon;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Sassnowski\LaravelWorkflow\Workflow;
use function PHPUnit\Framework\assertNull;
use Sassnowski\LaravelWorkflow\WorkflowJob;
use function PHPUnit\Framework\assertEquals;

uses(TestCase::class);

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
