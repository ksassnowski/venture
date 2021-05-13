<?php declare(strict_types=1);

use Stubs\TestJob1;
use Stubs\TestJob2;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;

uses(TestCase::class);

it('can handle old workflows that still saved serialized dependent jobs instead of step ids', function () {
    Bus::fake();

    $workflow = createWorkflow([
        'job_count' => 2,
        'jobs_processed' => 0,
    ]);
    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());
    $job1->dependantJobs = [$job2];
    $job2->withDependencies([TestJob1::class]);
    $workflow->addJobs(wrapJobsForWorkflow([$job1, $job2]));

    $workflow->onStepFinished($job1);

    Bus::assertDispatched(TestJob2::class);
});
