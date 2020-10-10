<?php declare(strict_types=1);

use Mockery as m;
use Stubs\TestJob1;
use Stubs\NonWorkflowJob;
use Sassnowski\Venture\Workflow;
use Illuminate\Contracts\Queue\Job;
use Opis\Closure\SerializableClosure;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertEquals;
use Sassnowski\Venture\WorkflowEventSubscriber;

uses(TestCase::class);

beforeEach(function () {
    $_SERVER['__catch.called'] = false;
});

function prepareFakeJob($workflowJob, bool $failed = false)
{
    return with(m::mock(Job::class), function (m\MockInterface $job) use ($workflowJob, $failed) {
        $job->allows('payload')->andReturns([
            'data' => [
                'command' => serialize($workflowJob),
            ]
        ]);
        $job->allows('hasFailed')->andReturn($failed);

        return $job;
    });
}

it('notifies the workflow if a workflow step has finished', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobProcessed('::connection::', prepareFakeJob($workflowJob));
    $eventSubscriber = new WorkflowEventSubscriber();

    assertFalse($workflow->isFinished());
    $eventSubscriber->handleJobProcessed($event);

    assertTrue($workflow->fresh()->isFinished());
});

it('only cares about jobs that use the WorkflowStep trait', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $event = new JobProcessed('::connection::', prepareFakeJob(new NonWorkflowJob()));
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobProcessed($event);

    assertFalse($workflow->fresh()->isFinished());
});

it('notifies the workflow if a job fails', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => serialize(SerializableClosure::from(function () {
            $_SERVER['__catch.called'] = true;
        })),
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', prepareFakeJob($workflowJob, true), new Exception());
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobFailed($event);

    assertTrue($_SERVER['__catch.called']);
});

it('does not notify the workflow is the job is not marked as failed', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => serialize(SerializableClosure::from(function () {
            $_SERVER['__catch.called'] = true;
        })),
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', prepareFakeJob($workflowJob, false), new Exception());
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobFailed($event);

    assertFalse($_SERVER['__catch.called']);
});

it('passes the exception along to the workflow', function () {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => serialize(SerializableClosure::from(function (Workflow $w, Throwable $e) {
            assertEquals('::message::', $e->getMessage());
        })),
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', prepareFakeJob($workflowJob, false), new Exception('::message::'));
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobFailed($event);
});
