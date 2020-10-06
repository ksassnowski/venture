<?php declare(strict_types=1);

use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Illuminate\Support\Facades\Bus;
use Sassnowski\LaravelWorkflow\Workflow;
use function Pest\Laravel\assertDatabaseHas;
use function PHPUnit\Framework\assertEquals;
use Sassnowski\LaravelWorkflow\PendingWorkflow;

uses(TestCase::class);

it('can be constructed with initial jobs', function () {
    $pendingWorkflow = new PendingWorkflow([new TestJob1(), new TestJob2()]);

    assertEquals(2, $pendingWorkflow->jobCount());
});

it('counts both initial and subsequent jobs in its total job count', function () {
    $pendingWorkflow = new PendingWorkflow([new TestJob1(), new TestJob2()]);

    $pendingWorkflow->addJob(new TestJob3(), []);

    assertEquals(3, $pendingWorkflow->jobCount());
});

it('creates a workflow', function () {
    Bus::fake();

    $pendingWorkflow = new PendingWorkflow([new TestJob1()]);
    $pendingWorkflow->addJob(new TestJob2(), [TestJob1::class]);

    $pendingWorkflow->start();

    assertDatabaseHas('workflows', [
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('sets a reference to the workflow on each job', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);
    $pendingWorkflow->addJob($testJob2, [TestJob1::class]);

    $pendingWorkflow->start();

    $workflowId = Workflow::first()->id;
    assertEquals($workflowId, $testJob1->workflowId);
    assertEquals($workflowId, $testJob2->workflowId);
});

it('sets the job dependencies on the job instances', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);
    $pendingWorkflow->addJob($testJob2, [TestJob1::class]);

    $pendingWorkflow->start();

    assertEquals([TestJob1::class], $testJob2->dependencies);
    assertEquals([], $testJob1->dependencies);
});

it('sets the dependants of a job', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);
    $pendingWorkflow->addJob($testJob2, [TestJob1::class]);

    $pendingWorkflow->start();

    assertEquals([$testJob2], $testJob1->dependantJobs);
    assertEquals([], $testJob2->dependantJobs);
});

it('saves the workflow steps to the database', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);
    $pendingWorkflow->addJob($testJob2, [TestJob1::class]);

    $pendingWorkflow->start();

    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob1)]);
    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob2)]);
});

it('uses the class name as the jobs name if no name was provided', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);

    $pendingWorkflow->start();

    assertDatabaseHas('workflow_jobs', ['name' => TestJob1::class]);
});

it('uses the nice name if it was provided', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);
    $pendingWorkflow->addJob($testJob2, [TestJob1::class], '::job-name::');

    $pendingWorkflow->start();

    assertDatabaseHas('workflow_jobs', ['name' => '::job-name::']);
});

it('creates workflow step records that use the jobs uuid', function () {
    Bus::fake();

    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $pendingWorkflow = new PendingWorkflow([$testJob1]);
    $pendingWorkflow->addJob($testJob2, [TestJob1::class], '::job-name::');

    $pendingWorkflow->start();

    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob1->stepId]);
    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob2->stepId]);
});
