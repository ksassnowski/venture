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

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Mockery as m;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowEventSubscriber;
use Stubs\LegacyJob;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__catch.called'] = false;
});

function prepareFakeJob(object $workflowJob, bool $failed = false, bool $released = false)
{
    return with(m::mock(Job::class), function (m\MockInterface $job) use ($workflowJob, $failed, $released) {
        $job->allows('payload')->andReturns([
            'data' => [
                'command' => \serialize($workflowJob),
            ],
        ]);
        $job->allows('hasFailed')->andReturn($failed);
        $job->allows('isReleased')->andReturn($released);
        $job->allows('delete');

        return $job;
    });
}

dataset('workflow step provider', [
    'regular job' => [new TestJob1()],
    'legacy job' => [new LegacyJob()],
]);

it('notifies the workflow if a workflow step has finished', function (object $workflowJob): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $event = new JobProcessed('::connection::', prepareFakeJob($workflowJob));
    $eventSubscriber = new WorkflowEventSubscriber();

    assertFalse($workflow->isFinished());
    $eventSubscriber->handleJobProcessed($event);

    assertTrue($workflow->fresh()->isFinished());
})->with('workflow step provider');

it('does not notify the workflow has finished when job is released back into the queue', function (object $workflowJob): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $event = new JobProcessed('::connection::', prepareFakeJob($workflowJob, false, true));
    $eventSubscriber = new WorkflowEventSubscriber();

    assertTrue($event->job->isReleased());
    assertFalse($workflow->isFinished());
    $eventSubscriber->handleJobProcessed($event);

    assertFalse($workflow->fresh()->isFinished());
})->with('workflow step provider');

it('does not care about non-workflow jobs', function (): void {
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

it('notifies the workflow if a job fails', function (object $workflowJob): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => \serialize(SerializableClosure::from(function (): void {
            $_SERVER['__catch.called'] = true;
        })),
    ]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', prepareFakeJob($workflowJob, true), new Exception());
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobFailed($event);

    assertTrue($_SERVER['__catch.called']);
})->with('workflow step provider');

it('does not notify the workflow is the job is not marked as failed', function (object $workflowJob): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => \serialize(SerializableClosure::from(function (): void {
            $_SERVER['__catch.called'] = true;
        })),
    ]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', prepareFakeJob($workflowJob, false), new Exception());
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobFailed($event);

    assertFalse($_SERVER['__catch.called']);
})->with('workflow step provider');

it('passes the exception along to the workflow', function (object $workflowJob): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => \serialize(SerializableClosure::from(function (Workflow $w, Throwable $e): void {
            assertEquals('::message::', $e->getMessage());
        })),
    ]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', prepareFakeJob($workflowJob, false), new Exception('::message::'));
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->handleJobFailed($event);
})->with('workflow step provider');

it('will delete the job if the workflow it belongs to has been cancelled', function (object $workflowJob): void {
    $workflow = createWorkflow(['cancelled_at' => now()]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $laravelJob = prepareFakeJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->onJobProcessing($event);

    $laravelJob->shouldHaveReceived('delete');
})->with('workflow step provider');

it('only cares about workflow jobs when checking for cancelled workflows', function (): void {
    $workflowJob = new NonWorkflowJob();
    $laravelJob = prepareFakeJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->onJobProcessing($event);

    $laravelJob->shouldNotHaveReceived('delete');
});

it('does not delete a job if its workflow has not been cancelled', function (object $workflowJob): void {
    $workflow = createWorkflow(['cancelled_at' => null]);
    $workflowJob = $workflowJob->withWorkflowId($workflow->id);
    $laravelJob = prepareFakeJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);
    $eventSubscriber = new WorkflowEventSubscriber();

    $eventSubscriber->onJobProcessing($event);

    $laravelJob->shouldNotHaveReceived('delete');
})->with('workflow step provider');
