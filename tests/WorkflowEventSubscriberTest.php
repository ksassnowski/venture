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

use Hamcrest\Core\IsEqual;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Mockery as m;
use Sassnowski\Venture\Actions\HandlesFailedJobs;
use Sassnowski\Venture\Actions\HandlesFinishedJobs;
use Sassnowski\Venture\Events\JobFailed as JobFailedEvent;
use Sassnowski\Venture\Events\JobFinished;
use Sassnowski\Venture\Events\JobProcessing as JobProcessingEvent;
use Sassnowski\Venture\Serializer\DefaultSerializer;
use Sassnowski\Venture\UnserializeJobExtractor;
use Sassnowski\Venture\WorkflowEventSubscriber;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;

uses(TestCase::class);

beforeEach(function (): void {
    Event::fake([
        JobProcessingEvent::class,
        JobFailedEvent::class,
        JobFinished::class,
    ]);
});

it('handles processed steps', function (): void {
    $handleFinishedJobs = m::spy(HandlesFinishedJobs::class);
    $workflowJob = new TestJob1();
    $event = new JobProcessed('::connection::', createQueueJob($workflowJob));

    createEventSubscriber($handleFinishedJobs)
        ->handleJobProcessed($event);

    $handleFinishedJobs
        ->shouldHaveBeenCalled()
        ->once()
        ->with(IsEqual::equalTo($workflowJob));
});

it('fires an event after a job has been processed', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessed('::connection::', $laravelJob);

    createEventSubscriber()
        ->handleJobProcessed($event);

    Event::assertDispatched(
        JobFinished::class,
        fn (JobFinished $event): bool => $event->job == $workflowJob,
    );
});

it('does not handle processed jobs if the job has been released back to the queue', function (): void {
    $workflowJob = new TestJob1();
    $event = new JobProcessed('::connection::', createQueueJob($workflowJob, false, true));
    $handleFinishedJobs = m::spy(HandlesFinishedJobs::class);

    expect($event->job)->isReleased()->toBeTrue();
    createEventSubscriber($handleFinishedJobs)->handleJobProcessed($event);

    $handleFinishedJobs->shouldNotHaveBeenCalled();
});

it('does not handle processed non-workflow jobs', function (): void {
    $event = new JobProcessed('::connection::', createQueueJob(new NonWorkflowJob()));
    $handleFinishedJobs = m::spy(HandlesFinishedJobs::class);

    createEventSubscriber($handleFinishedJobs)
        ->handleJobProcessed($event);

    $handleFinishedJobs->shouldNotHaveBeenCalled();
});

it('handles failed jobs', function (): void {
    $workflowJob = new TestJob1();
    $exception = new Exception();
    $event = new JobFailed('::connection::', createQueueJob($workflowJob, true), $exception);
    $handleFailedJobs = m::spy(HandlesFailedJobs::class);

    createEventSubscriber(handleFailedJobs: $handleFailedJobs)
        ->handleJobFailed($event);

    $handleFailedJobs
        ->shouldHaveBeenCalled()
        ->once()
        ->with(IsEqual::equalTo($workflowJob), $exception);
});

it('does not notify the workflow is the job is not marked as failed', function (): void {
    $workflowJob = new TestJob1();
    $handleFailedJobs = m::spy(HandlesFailedJobs::class);
    $event = new JobFailed('::connection::', createQueueJob($workflowJob, false), new Exception());

    createEventSubscriber(handleFailedJobs: $handleFailedJobs)
        ->handleJobFailed($event);

    $handleFailedJobs->shouldNotHaveBeenCalled();
});

it('does not handle failed non-workflow jobs', function (): void {
    $handleFailedJobs = m::spy(HandlesFailedJobs::class);
    $event = new JobFailed('::connection::', createQueueJob(new NonWorkflowJob(), true), new Exception());

    createEventSubscriber(handleFailedJobs: $handleFailedJobs)
        ->handleJobFailed($event);

    $handleFailedJobs->shouldNotHaveBeenCalled();
});

it('will delete the job if the workflow it belongs to has been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => now()]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    createEventSubscriber()->onJobProcessing($event);

    $laravelJob->shouldHaveReceived('delete');
});

it('only cares about workflow jobs when checking for cancelled workflows', function (): void {
    $workflowJob = new NonWorkflowJob();
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    createEventSubscriber()->onJobProcessing($event);

    $laravelJob->shouldNotHaveReceived('delete');
});

it('does not delete a job if its workflow has not been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => null]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    createEventSubscriber()->onJobProcessing($event);

    $laravelJob->shouldNotHaveReceived('delete');
});

it('fires an event when a workflow step gets processed and the job has not been cancelled', function (): void {
    $workflow = createWorkflow(['cancelled_at' => null]);
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob);
    $event = new JobProcessing('::connection::', $laravelJob);

    createEventSubscriber()->onJobProcessing($event);

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

    createEventSubscriber()->onJobProcessing($event);

    Event::assertNotDispatched(JobProcessingEvent::class);
});

it('does not fire an event when a job is processing but it\'s not a workflow step', function (): void {
    $laravelJob = createQueueJob(new NonWorkflowJob());
    $event = new JobProcessing('::connection::', $laravelJob);

    createEventSubscriber()->onJobProcessing($event);

    Event::assertNotDispatched(JobProcessingEvent::class);
});

it('does not fire an event after a job has been processed if the job has been released', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $laravelJob = createQueueJob($workflowJob, released: true);
    $event = new JobProcessed('::connection::', $laravelJob);

    createEventSubscriber()->handleJobProcessed($event);

    Event::assertNotDispatched(JobFinished::class);
});

it('does not fire an event after a job has been processed if the job is not a workflow job', function (): void {
    $laravelJob = createQueueJob(new NonWorkflowJob());
    $event = new JobProcessed('::connection::', $laravelJob);

    createEventSubscriber()->handleJobProcessed($event);

    Event::assertNotDispatched(JobFinished::class);
});

it('fires an event after a job has failed', function (): void {
    $workflow = createWorkflow();
    $workflowJob = (new TestJob1())->withWorkflowId($workflow->id);
    $exception = new Exception();
    $laravelJob = createQueueJob($workflowJob, failed: true);
    $event = new JobFailed('::connection::', $laravelJob, $exception);

    createEventSubscriber()->handleJobFailed($event);

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

    createEventSubscriber()->handleJobFailed($event);

    Event::assertNotDispatched(JobFailedEvent::class);
});

it('does not fire an event after a job has failed if the job is not a workflow job', function (): void {
    $exception = new Exception();
    $laravelJob = createQueueJob(new NonWorkflowJob());
    $event = new JobFailed('::connection::', $laravelJob, $exception);

    createEventSubscriber()->handleJobFailed($event);

    Event::assertNotDispatched(JobFailedEvent::class);
});

function createEventSubscriber(
    ?HandlesFinishedJobs $handleFinishedJobs = null,
    ?HandlesFailedJobs $handleFailedJobs = null,
): WorkflowEventSubscriber {
    return new WorkflowEventSubscriber(
        new UnserializeJobExtractor(new DefaultSerializer()),
        $handleFinishedJobs ?: m::spy(HandlesFinishedJobs::class),
        $handleFailedJobs ?: m::spy(HandlesFailedJobs::class),
    );
}
