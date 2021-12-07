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
use Sassnowski\Venture\WorkflowEventSubscriber;

uses(TestCase::class);

it('registers the event subscriber', function (): void {
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
