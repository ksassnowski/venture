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
use Illuminate\Support\Facades\Event;
use Laravel\SerializableClosure\SerializableClosure;
use Sassnowski\Venture\Events\JobFailed as JobFailedEvent;
use Sassnowski\Venture\Events\JobFinished;
use Sassnowski\Venture\Events\JobProcessing as JobProcessingEvent;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Serializer\DefaultSerializer;
use Sassnowski\Venture\UnserializeJobExtractor;
use Sassnowski\Venture\WorkflowEventSubscriber;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    Event::fake([
        JobProcessingEvent::class,
        JobFailedEvent::class,
        JobFinished::class,
    ]);
    $_SERVER['__catch.called'] = false;
    $this->eventSubscriber = new WorkflowEventSubscriber(
        new UnserializeJobExtractor(new DefaultSerializer()),
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
        'catch_callback' => \serialize(new SerializableClosure(function (): void {
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
        'catch_callback' => \serialize(new SerializableClosure(function (): void {
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
        'catch_callback' => \serialize(new SerializableClosure(function (Workflow $w, Throwable $e): void {
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

it('fires an event when a workflow step gets processed and the job has not been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => null]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    $this->eventSubscriber->onJobProcessing($event);

    Event::assertDispatched(
        JobProcessingEvent::class,
        fn (JobProcessingEvent $event): bool => $event->job == $workflowJob,
    );
});

it('does not fire an event when a job is processing but the job has been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => now()]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    $this->eventSubscriber->onJobProcessing($event);

    Event::assertNotDispatched(JobProcessingEvent::class);
});

it('does not fire an event when a job is processing but it\'s not a workflow step', function (): void {
    $laravelJob = createQueueJob(new NonWorkflowJob());
    $event = new JobProcessing('::connection::', $laravelJob);

    $this->eventSubscriber->onJobProcessing($event);

    Event::assertNotDispatched(JobProcessingEvent::class);
});

it('fires an event after a job has been processed', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessed('::connection::', $laravelJob);

    $this->eventSubscriber->handleJobProcessed($event);

    Event::assertDispatched(
        JobFinished::class,
        fn (JobFinished $event): bool => $event->job == $workflowJob,
    );
});

it('does not fire an event after a job has been processed if the job has been released', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob, released: true);
    $event = new JobProcessed('::connection::', $laravelJob);

    $this->eventSubscriber->handleJobProcessed($event);

    Event::assertNotDispatched(JobFinished::class);
});

it('does not fire an event after a job has been processed if the job is not a workflow job', function (): void {
    $laravelJob = createQueueJob(new NonWorkflowJob());
    $event = new JobProcessed('::connection::', $laravelJob);

    $this->eventSubscriber->handleJobProcessed($event);

    Event::assertNotDispatched(JobFinished::class);
});

it('fires an event after a job has failed', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $exception = new Exception();
    $laravelJob = createQueueJob($workflowJob, failed: true);
    $event = new JobFailed('::connection::', $laravelJob, $exception);

    $this->eventSubscriber->handleJobFailed($event);

    Event::assertDispatched(
        JobFailedEvent::class,
        function (JobFailedEvent $event) use ($workflowJob, $exception): bool {
            return $event->job == $workflowJob
                && $event->exception === $exception;
        },
    );
});

it('does not fire an event after a job has failed if the job has not been marked as failed', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $exception = new Exception();
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobFailed('::connection::', $laravelJob, $exception);

    $this->eventSubscriber->handleJobFailed($event);

    Event::assertNotDispatched(JobFailedEvent::class);
});

it('does not fire an event after a job has failed if the job is not a workflow job', function (): void {
    $exception = new Exception();
    $laravelJob = createQueueJob(new NonWorkflowJob());
    $event = new JobFailed('::connection::', $laravelJob, $exception);

    $this->eventSubscriber->handleJobFailed($event);

    Event::assertNotDispatched(JobFailedEvent::class);
});
