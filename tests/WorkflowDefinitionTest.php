<?php declare(strict_types=1);

use Carbon\Carbon;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;
use Stubs\TestJob5;
use Stubs\TestJob6;
use Stubs\LegacyJob;
use Stubs\NestedWorkflow;
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
use Sassnowski\Venture\Workflow\LegacyWorkflowStepAdapter;

uses(TestCase::class);

beforeEach(function () {
    Bus::fake();
    $_SERVER['__before_connect_callback'] = 0;
});

it('creates a workflow', function () {
    (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), dependencies: [TestJob1::class])
        ->addJob(new LegacyJob())
        ->build();

    assertDatabaseHas('workflows', [
        'job_count' => 3,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => json_encode([]),
    ]);
});

it('returns the workflow\'s initial batch of jobs', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new LegacyJob();

    [$workflow, $initialBatch] = (new WorkflowDefinition())
        ->addJob($job1)
        ->addJob($job2)
        ->addJob(new TestJob3(), dependencies: [TestJob1::class])
        ->addJob($job3)
        ->build();

    assertEquals([$job1, $job2, LegacyWorkflowStepAdapter::from($job3)], $initialBatch);
});

it('returns the workflow', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    [$workflow, $initialBatch] = (new WorkflowDefinition())
        ->addJob($job1)
        ->addJob($job2)
        ->addJob(new TestJob3(), dependencies: [TestJob1::class])
        ->build();

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('sets a reference to the workflow on each job', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    $workflow = Workflow::first();
    assertTrue($testJob1->workflow()->is($workflow));
    assertTrue($testJob2->workflow()->is($workflow));
});

it('sets the job dependencies on the job instances', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $testJob3 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class], id: '::job-2-id::')
        ->addJob($testJob3, dependencies: ['::job-2-id::'])
        ->build();

    assertEquals([TestJob1::class], $testJob2->getDependencies());
    assertEquals([], $testJob1->getDependencies());
    assertEquals(['::job-2-id::'], $testJob3->getDependencies());
});

it('sets the jobId on the job instance', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, id: '::job-2-id::')
        ->build();

    assertEquals(TestJob1::class, $testJob1->getJobId());
    assertEquals('::job-2-id::', $testJob2->getJobId());
});

it('sets the dependants of a job', function () {
    Bus::fake();
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    assertEquals([$testJob2->getStepId()], $testJob1->getDependantJobs());
    assertEquals([], $testJob2->getDependantJobs());
});

it('saves the workflow steps to the database', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob1)]);
    assertDatabaseHas('workflow_jobs', ['job' => serialize($testJob2)]);
});

it('saves the list of edges for each job', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    [$workflow, $initialBatch] = (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    $jobs = $workflow->jobs;
    assertEquals(
        [$testJob2->getStepId()],
        $jobs->firstWhere('uuid', $testJob1->getStepId())->edges
    );
    assertEquals(
        [],
        $jobs->firstWhere('uuid', $testJob2->getStepId())->edges
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
        ->addJob(new TestJob2(), dependencies: [TestJob1::class], name: '::job-name::')
        ->build();

    assertDatabaseHas('workflow_jobs', ['name' => '::job-name::']);
});

it('creates workflow step records that use the jobs uuid', function () {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    (new WorkflowDefinition())
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class], name: '::job-name::')
        ->build();

    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob1->getStepId()]);
    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob2->getStepId()]);
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
        ->addJob(new TestJob1(), delay: $delay);
    $workflow2 = WorkflowFacade::define('::name-2::')
        ->addJob(new LegacyJob(), delay: $delay);

    assertTrue($workflow1->hasJobWithDelay(TestJob1::class, $delay));
    assertTrue($workflow2->hasJobWithDelay(LegacyJob::class, $delay));
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
        ->addJob(new TestJob2(), dependencies: [TestJob1::class]);

    assertTrue($definition->hasJob(TestJob2::class, [TestJob1::class]));
});

it('returns false if job exists, but with incorrect dependencies', function () {
    $definition = WorkflowFacade::define('::name::')
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), dependencies: [TestJob2::class]);

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
        ->addJob(new TestJob1(), delay: $delay);

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
        ->addJob(new TestJob1(), dependencies: [])
        ->build($callback);

    assertEquals('::new-name::', $workflow->name);
});

it('calls the before connecting hook before adding a nested workflow', function () {
    $workflow = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob2());
        }

        public function beforeNesting(array $jobs): void
        {
            $_SERVER['__before_connect_callback']++;
        }
    };

    WorkflowFacade::define('::name::')
        ->addWorkflow(new $workflow(), []);

    assertEquals(1, $_SERVER['__before_connect_callback']);
});

it('can add another workflow', function () {
    $workflow = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob4())
                ->addJob(new TestJob5())
                ->addJob(new TestJob6(), dependencies: [TestJob4::class]);
        }
    };
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), dependencies: [TestJob1::class])
        ->addWorkflow($workflow, dependencies: [TestJob1::class]);

    assertTrue($definition->hasJobWithDependencies($workflow::class . '.' . TestJob4::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies($workflow::class . '.' . TestJob5::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies($workflow::class . '.' . TestJob6::class, [$workflow::class . '.' . TestJob4::class]));
});

it('adding another workflow namespaces the nested workflow\'s job ids', function () {
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addWorkflow(new NestedWorkflow());

    assertTrue($definition->hasJob(NestedWorkflow::class . '.' . TestJob1::class));
    assertTrue($definition->hasJob(TestJob1::class));
    assertTrue($definition->hasJobWithDependencies(TestJob2::class, [TestJob1::class]));
});

it('adding another workflow updates the job id on nested job instances', function () {
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addWorkflow(new NestedWorkflow($job = new TestJob1()));

    assertEquals(NestedWorkflow::class . '.' . TestJob1::class, $job->getJobId());
});

it('allows multiple instances of the same job with explicit ids', function () {
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1(), id: '::id-1::')
        ->addJob(new TestJob1(), id: '::id-2::');

    assertTrue($definition->hasJob('::id-1::'));
    assertTrue($definition->hasJob('::id-2::'));
});

it('uses a legacy job\'s wrapped class name as the id if no explicit id was provided ', function () {
    $definition = (new WorkflowDefinition())
        ->addJob(new LegacyJob());

    expect($definition)->hasJob(LegacyJob::class)->toBeTrue();
});

it('can allows FQCN and explicit id when declaring dependencies', function () {
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1())
        ->addJob(new TestJob1(), id: '::id::')
        ->addJob(new TestJob2(), dependencies: [TestJob1::class])
        ->addJob(new TestJob3(), dependencies: ['::id::']);

    assertTrue($definition->hasJobWithDependencies(TestJob2::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies(TestJob3::class, ['::id::']));
});

it('can add multiple instances of the same workflow if they have different ids', function () {
    $workflow = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob2(), id: '::job-2-id::');
        }
    };
    $definition = (new WorkflowDefinition())
        ->addJob(new TestJob1(), id: '::job-1-id::')
        ->addWorkflow($workflow, dependencies: ['::job-1-id::'], id: '::workflow-1-id::')
        ->addWorkflow($workflow, dependencies: ['::job-1-id::'], id: '::workflow-2-id::');

    assertTrue($definition->hasJobWithDependencies('::workflow-1-id::.::job-2-id::', ['::job-1-id::']));
    assertTrue($definition->hasJobWithDependencies('::workflow-2-id::.::job-2-id::', ['::job-1-id::']));
});

it('can check if a workflow contains a nested workflow', function (callable $configureWorkflow, ?array $dependencies, bool $expected) {
    $definition = new WorkflowDefinition();

    $configureWorkflow($definition);

    assertEquals($expected, $definition->hasWorkflow(NestedWorkflow::class, $dependencies));
})->with([
    'has workflow, ignore dependencies' => [
        'configureWorkflow' => fn () => function (WorkflowDefinition $definition) {
            $definition->addWorkflow(new NestedWorkflow());
        },
        'dependencies' => null,
        'expected' => true,
    ],
    'does not have workflow, ignore dependencies' => [
        'configureWorkflow' => fn () => function (WorkflowDefinition $definition) {},
        'dependencies' => null,
        'expected' => false,
    ],
    'has workflow, incorrect dependencies' => [
        'configureWorkflow' => fn () => function (WorkflowDefinition $definition) {
            $definition
                ->addWorkflow(new NestedWorkflow());
        },
        'dependencies' => [TestJob1::class],
        'expected' => false,
    ],
    'has workflow, correct dependencies' => [
        'configureWorkflow' => fn () => function (WorkflowDefinition $definition) {
            $definition
                ->addJob(new TestJob1())
                ->addWorkflow(new NestedWorkflow(), [TestJob1::class]);
        },
        'dependencies' => [TestJob1::class],
        'expected' => true,
    ],
]);

class DummyCallback
{
    public function __invoke()
    {
        echo 'herp';
    }
}
