<?php declare(strict_types=1);

use Mockery as m;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Sassnowski\Venture\WorkflowEventSubscriber;

uses(TestCase::class);

it('registers the event subscriber', function () {
    $eventSubscriberMock = m::spy(WorkflowEventSubscriber::class);
    app()->instance(WorkflowEventSubscriber::class, $eventSubscriberMock);
    $jobMock = m::mock(Job::class);

    event(new JobProcessed('::connection::', $jobMock));
    $eventSubscriberMock->shouldHaveReceived('handleJobProcessed');

    event(new JobProcessing('::connection::', $jobMock));
    $eventSubscriberMock->shouldHaveReceived('onJobProcessing');

    event(new JobFailed('::connection::', $jobMock, new Exception()));
    $eventSubscriberMock->shouldHaveReceived('handleJobFailed');
});
