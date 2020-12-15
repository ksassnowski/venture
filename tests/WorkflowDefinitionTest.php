<?php declare(strict_types=1);

use Carbon\Carbon;
use Stubs\TestAbstractWorkflow;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;
use Stubs\TestJob5;
use Stubs\TestJob6;
use Stubs\NonQueueableJob;
use Illuminate\Support\Facades\Bus;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\AbstractWorkflow;
use function PHPUnit\Framework\assertTrue;
use Sassnowski\Venture\WorkflowDefinition;
use function PHPUnit\Framework\assertFalse;
use function Pest\Laravel\assertDatabaseHas;
use function PHPUnit\Framework\assertEquals;
use Sassnowski\Venture\Facades\Workflow as WorkflowFacade;
use Sassnowski\Venture\Exceptions\NonQueueableWorkflowStepException;

uses(TestCase::class);

beforeEach(function () {
    Bus::fake();
});

it('creates a workflow', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->build();

    assertDatabaseHas('workflows', [
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('creates a workflow with identical job classes', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->build();

    assertDatabaseHas('workflows', [
        'job_count' => 3,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('creates a workflow with identical job classes as dependencies', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addJob(new TestJob2(), [TestJob1::class])
        ->build();

    assertDatabaseHas('workflows', [
        'job_count' => 4,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('creates a workflow with job class dependant on itself', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob1(), [TestJob1::class])
        ->build();

    assertDatabaseHas('workflows', [
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('returns the workflow\'s initial batch of jobs', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    [$workflow, $initialBatch] = (new WorkflowDefinition())
        ->addJob($job1)
        ->addJob($job2)
        ->addJob(new TestJob3(), [TestJob1::class])
        ->build();

    assertEquals([$job1, $job2], $initialBatch);
});

it('returns the workflow', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    [$workflow, $initialBatch] = (new WorkflowDefinition())
        ->addJob($job1)
        ->addJob($job2)
        ->addJob(new TestJob3(), [TestJob1::class])
        ->build();

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('sets a reference to the workflow on each job', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->build();

    $workflowId = Workflow::first()->id;
    assertEquals($workflowId, $testJob1->workflowId);
    assertEquals($workflowId, $testJob2->workflowId);
});

it('sets the job dependencies on the job instances', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->build();

    assertEquals([TestJob1::class], $testJob2->dependencies);
    assertEquals([], $testJob1->dependencies);
});

it('sets the dependants of a job', function () {
    Bus::fake();
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->build();

    assertEquals([$testJob2], $testJob1->dependantJobs);
    assertEquals([], $testJob2->dependantJobs);
});

it('saves the workflow steps to the database', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->build();

    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob1)]);
    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob2)]);
});

it('saves the list of edges for each job', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    [$workflow, $initialBatch] = (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class])
        ->build();

    $jobs = $workflow->jobs;
    assertEquals(
        [$testJob2->stepId],
        $jobs->firstWhere('uuid', $testJob1->stepId)->edges
    );
    assertEquals(
        [],
        $jobs->firstWhere('uuid', $testJob2->stepId)->edges
    );
});

it('uses the class name as the jobs name if no name was provided', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->build();

    assertDatabaseHas('workflow_jobs', ['name' => TestJob1::class]);
});

it('uses the nice name if it was provided', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class], '::job-name::')
        ->build();

    assertDatabaseHas('workflow_jobs', ['name' => '::job-name::']);
});

it('creates workflow step records that use the jobs uuid', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, [TestJob1::class], '::job-name::')
        ->build();

    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob1->stepId]);
    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob2->stepId]);
});

it('creates a workflow with the provided name', function () {
    [$workflow, $initialBatch] = WorkflowFacade::define('::workflow-name::')
        ->addJob(new TestJob1())
        ->build();

    assertEquals('::workflow-name::', $workflow->name);
});

it('allows configuration of a then callback', function () {
    $callback = function (Workflow $wf) {
        echo 'derp';
    };
    [$workflow, $initialBatch] = WorkflowFacade::define('::name::')
        ->then($callback)
        ->build();

    assertEquals($workflow->then_callback, serialize(SerializableClosure::from($callback)));
});

it('allows configuration of an invokable class as then callback', function () {
    $callback = new DummyCallback();

    [$workflow, $initialBatch] = WorkflowFacade::define('::name::')
        ->then($callback)
        ->build();

    assertEquals($workflow->then_callback, serialize($callback));
});

it('allows configuration of a catch callback', function () {
    $callback = function (Workflow $wf) {
        echo 'derp';
    };
    [$workflow, $initialBatch] = WorkflowFacade::define('::name::')
        ->catch($callback)
        ->build();

    assertEquals($workflow->catch_callback, serialize(SerializableClosure::from($callback)));
});

it('allows configuration of an invokable class as catch callback', function () {
    $callback = new DummyCallback();

    [$workflow, $initialBatch] = WorkflowFacade::define('::name::')
        ->catch($callback)
        ->build();

    assertEquals($workflow->catch_callback, serialize($callback));
});

it('can add a job with a delay', function ($delay) {
    Carbon::setTestNow(now());

    $workflow1 = WorkflowFacade::define('::name-1::')
        ->addJobWithDelay(new TestJob1(), $delay);
    $workflow2 = WorkflowFacade::define('::name-2::')
        ->addJobWithDelay(new TestJob2(), $delay);

    assertTrue($workflow1->hasJobWithDelay(TestJob1::class, $delay));
    assertTrue($workflow2->hasJobWithDelay(TestJob2::class, $delay));
})->with('delay provider');

it('returns true if job is part of the workflow', function () {
    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob1());

    assertTrue($definition->hasJob(TestJob1::class));
});

it('returns false if job is not part of the workflow', function () {
    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob2());

    assertFalse($definition->hasJob(TestJob1::class));
});

it('returns true if job exists with the correct dependencies', function () {
    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class]);

    assertTrue($definition->hasJob(TestJob2::class, [TestJob1::class]));
});

it('returns true if job exists with the correct instance dependencies', function () {
    $definition = WorkflowFacade::define('::name::')
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2(), [$job1]);

    assertTrue($definition->hasJob(TestJob2::class, [$job1]));
});

it('returns false if job exists, but with incorrect dependencies', function () {
    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), [TestJob2::class]);

    assertFalse($definition->hasJob(TestJob3::class, [TestJob1::class]));
});

it('returns false if job exists without delay', function () {
    Carbon::setTestNow(now());

    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob1());

    assertFalse($definition->hasJob(TestJob1::class, [], now()->addDay()));
});

it('returns true if job exists with correct delay', function ($delay) {
    Carbon::setTestNow(now());

    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob1(), [], '', $delay);

    assertTrue($definition->hasJob(TestJob1::class, [], $delay));
})->with('delay provider');

dataset('delay provider', [
    'carbon date' => [now()->addHour()],
    'integer' => [2000],
    'date interval' => [new DateInterval('P14D')],
]);

it('calls the before create hook before saving the workflow if provided', function () {
    $callback = function (Workflow $workflow) {
        $workflow->name = '::new-name::';
    };

    [$workflow, $initialBatch] = WorkflowFacade::define('::old-name::')
        ->addJob(new TestJob1(), [])
        ->build($callback);

    assertEquals('::new-name::', $workflow->name);
});

it('can add another workflow', function () {
    $workflow = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob4())
                ->addJob(new TestJob5())
                ->addJob(new TestJob6(), [TestJob4::class]);
        }
    };
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), [TestJob1::class])
        ->addWorkflow($workflow, [TestJob1::class]);

    assertTrue($definition->hasJobWithDependencies(TestJob4::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies(TestJob5::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies(TestJob6::class, [TestJob4::class]));
});

it('returns true if workflow contains workflow instance with the correct dependencies', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addWorkflow($workflow, [TestJob1::class]);

    assertTrue($definition->hasWorkflow($workflow, [TestJob1::class]));
});

it('returns false if workflow contains workflow instance, but with incorrect dependencies', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addWorkflow($workflow, [TestJob1::class]);

    assertFalse($definition->hasWorkflow($workflow, [TestJob2::class]));
});

it('returns true if workflow contains workflow instance with the correct dependency instances', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), [TestJob1::class])
        ->addWorkflow($workflow, [$job1]);

    assertTrue($definition->hasWorkflow($workflow, [$job1]));
});

it('returns true if workflow contains workflow class with the correct dependency instances', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addJob($job1 = new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), [TestJob1::class])
        ->addWorkflow($workflow, [$job1]);

    assertTrue($definition->hasWorkflow(get_class($workflow), [$job1]));
});

it('returns false if workflow contains workflow instance, but with incorrect dependency instances', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addJob($job1 = new TestJob1())
        ->addJob($job2 = new TestJob1())
        ->addJob(new TestJob3(), [TestJob1::class])
        ->addWorkflow($workflow, [$job2]);

    assertFalse($definition->hasWorkflow($workflow, [$job1]));
});

it('returns true if workflow contains workflow with the correct dependency instances when adding workflow multiple times', function () {
    $workflow = new TestAbstractWorkflow();
    $workflow2 = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addJob($job1 = new TestJob1())
        ->addJob($job2 = new TestJob2())
        ->addJob(new TestJob3(), [TestJob1::class])
        ->addWorkflow($workflow, [$job1])
        ->addWorkflow($workflow2, [$job2]);

    assertTrue($definition->hasWorkflow($workflow, [$job1]));
    assertTrue($definition->hasWorkflow($workflow2, [$job2]));
});

it('returns the added workflow by instance', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addWorkflow($workflow, []);

    assertEquals($workflow, $definition->getWorkflow($workflow));
});

it('returns the added workflow by class', function () {
    $workflow = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addWorkflow($workflow, []);

    assertEquals($workflow, $definition->getWorkflow(TestAbstractWorkflow::class));
});

it('returns the last added workflow when getting workflow by class', function () {
    $workflow1 = new TestAbstractWorkflow();
    $workflow2 = new TestAbstractWorkflow();
    $definition = (new WorkflowDefinition())
        ->addWorkflow($workflow1, [])
        ->addWorkflow($workflow2, []);

    assertEquals($workflow2, $definition->getWorkflow(TestAbstractWorkflow::class));
});

it('throws an exception when trying to add a job without the ShouldQueue interface', function () {
    (new WorkflowDefinition())->addJob(new NonQueueableJob());
})->expectException(NonQueueableWorkflowStepException::class);

class DummyCallback
{
    public function __invoke()
    {
        echo 'herp';
    }
}
