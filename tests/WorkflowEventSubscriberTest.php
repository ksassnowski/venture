<?php declare(strict_types=1);

use Mockery as m;
use Stubs\TestJob1;
use Stubs\NonWorkflowJob;
use Sassnowski\Venture\Workflow;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertFalse;
use Sassnowski\Venture\WorkflowEventSubscriber;

uses(TestCase::class);

function prepareFakeJob($workflowJob)
{
    return with(m::mock(Job::class), function (m\MockInterface $job) use ($workflowJob) {
        $job->allows('payload')->andReturns([
            'data' => [
                'command' => serialize($workflowJob),
            ]
        ]);

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
