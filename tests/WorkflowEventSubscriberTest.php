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

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\UnserializeJobExtractor;
use Sassnowski\Venture\WorkflowEventSubscriber;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__catch.called'] = false;
    $this->eventSubscriber = new WorkflowEventSubscriber(
        new UnserializeJobExtractor(),
    );
});

it('notifies the workflow if a workflow step has finished', function (): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobProcessed('::connection::', createQueueJob($workflowJob));

    assertFalse($workflow->isFinished());
    $this->eventSubscriber->handleJobProcessed($event);

    assertTrue($workflow->fresh()->isFinished());
});

it('does not notify the workflow has finished when job is released back into the queue', function (): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobProcessed('::connection::', createQueueJob($workflowJob, false, true));

    assertTrue($event->job->isReleased());
    assertFalse($workflow->isFinished());
    $this->eventSubscriber->handleJobProcessed($event);

    assertFalse($workflow->fresh()->isFinished());
});

it('only cares about jobs that use the WorkflowStep trait', function (): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ]);
    $event = new JobProcessed('::connection::', createQueueJob(new NonWorkflowJob()));

    $this->eventSubscriber->handleJobProcessed($event);

    assertFalse($workflow->fresh()->isFinished());
});

it('notifies the workflow if a job fails', function (): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => \serialize(SerializableClosure::from(function (): void {
            $_SERVER['__catch.called'] = true;
        })),
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', createQueueJob($workflowJob, true), new Exception());

    $this->eventSubscriber->handleJobFailed($event);

    assertTrue($_SERVER['__catch.called']);
});

it('does not notify the workflow is the job is not marked as failed', function (): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => \serialize(SerializableClosure::from(function (): void {
            $_SERVER['__catch.called'] = true;
        })),
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', createQueueJob($workflowJob, false), new Exception());

    $this->eventSubscriber->handleJobFailed($event);

    assertFalse($_SERVER['__catch.called']);
});

it('passes the exception along to the workflow', function (): void {
    $workflow = Workflow::create([
        'job_count' => 1,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
        'catch_callback' => \serialize(SerializableClosure::from(function (Workflow $w, Throwable $e): void {
            assertEquals('::message::', $e->getMessage());
        })),
    ]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $event = new JobFailed('::connection::', createQueueJob($workflowJob, false), new Exception('::message::'));

    $this->eventSubscriber->handleJobFailed($event);
});

it('will delete the job if the workflow it belongs to has been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => now()]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    $this->eventSubscriber->onJobProcessing($event);

    $laravelJob->shouldHaveReceived('delete');
});

it('only cares about workflow jobs when checking for cancelled workflows', function (): void {
    $workflowJob = new NonWorkflowJob();
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    $this->eventSubscriber->onJobProcessing($event);

    $laravelJob->shouldNotHaveReceived('delete');
});

it('does not delete a job if its workflow has not been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => null]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    $this->eventSubscriber->onJobProcessing($event);

    $laravelJob->shouldNotHaveReceived('delete');
});
