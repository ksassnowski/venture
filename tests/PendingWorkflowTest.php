<?php declare(strict_types=1);

use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Sassnowski\Venture\Workflow;
use Illuminate\Support\Facades\Bus;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\PendingWorkflow;
use function PHPUnit\Framework\assertTrue;
use function Pest\Laravel\assertDatabaseHas;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

uses(TestCase::class);

beforeEach(function () {
    Bus::fake();
});

it('creates a workflow', function () {
    (new PendingWorkflow())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->start();

    assertDatabaseHas('workflows', [
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('sets a reference to the workflow on each job', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new PendingWorkflow())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->start();

    $workflowId = Workflow::first()->id;
    assertEquals($workflowId, $testJob1->workflowId);
    assertEquals($workflowId, $testJob2->workflowId);
});

it('sets the job dependencies on the job instances', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new PendingWorkflow())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->start();

    assertEquals([TestJob1::class], $testJob2->dependencies);
    assertEquals([], $testJob1->dependencies);
});

it('sets the dependants of a job', function () {
    Bus::fake();
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new PendingWorkflow())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->start();

    assertEquals([$testJob2], $testJob1->dependantJobs);
    assertEquals([], $testJob2->dependantJobs);
});

it('saves the workflow steps to the database', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new PendingWorkflow())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->start();

    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob1)]);
    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob2)]);
});

it('uses the class name as the jobs name if no name was provided', function () {
    (new PendingWorkflow())
        ->addJob(new TestJob1())
        ->start();

    assertDatabaseHas('workflow_jobs', ['name' => TestJob1::class]);
});

it('uses the nice name if it was provided', function () {
    (new PendingWorkflow())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class], '::job-name::')
        ->start();

    assertDatabaseHas('workflow_jobs', ['name' => '::job-name::']);
});

it('creates workflow step records that use the jobs uuid', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new PendingWorkflow())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class], '::job-name::')
        ->start();

    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob1->stepId]);
    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob2->stepId]);
});

it('returns the created workflow', function () {
    $workflow = (new PendingWorkflow())
        ->addJob(new TestJob1())
        ->start();

    assertInstanceOf(Workflow::class, $workflow);
    assertTrue($workflow->exists);
});

test('jobs without dependencies should be part of the initial batch', function () {
    (new PendingWorkflow())
        ->addJob(new TestJob1(), [])
        ->addJob(new TestJob2(), [])
        ->addJob(new TestJob3(), [TestJob1::class])
        ->start();

    Bus::assertDispatched(TestJob1::class);
    Bus::assertDispatched(TestJob2::class);
});

it('creates a workflow with the provided name', function () {
    $workflow = Workflow::new('::workflow-name::')
        ->addJob(new TestJob1())
        ->start();

    assertEquals('::workflow-name::', $workflow->name);
});

it('allows configuration of a then callback', function () {
    $callback = function (Workflow $wf) {
        echo 'derp';
    };
    $workflow = Workflow::new('::name::')
        ->then($callback)
        ->start();

    assertEquals($workflow->then_callback, serialize(SerializableClosure::from($callback)));
});

it('allows configuration of an invokable class as then callback', function () {
    $callback = new DummyCallback();

    $workflow = Workflow::new('::name::')
        ->then($callback)
        ->start();

    assertEquals($workflow->then_callback, serialize($callback));
});

it('allows configuration of a catch callback', function () {
    $callback = function (Workflow $wf) {
        echo 'derp';
    };
    $workflow = Workflow::new('::name::')
        ->catch($callback)
        ->start();

    assertEquals($workflow->catch_callback, serialize(SerializableClosure::from($callback)));
});

it('allows configuration of an invokable class as catch callback', function () {
    $callback = new DummyCallback();

    $workflow = Workflow::new('::name::')
        ->catch($callback)
        ->start();

    assertEquals($workflow->catch_callback, serialize($callback));
});

class DummyCallback
{
    public function __invokable()
    {
        echo 'herp';
    }
}
