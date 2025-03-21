<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Laravel\SerializableClosure\SerializableClosure;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Events\JobAdded;
use Sassnowski\Venture\Events\WorkflowAdded;
use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Exceptions\InvalidJobException;
use Sassnowski\Venture\FakeIDGenerator;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\StepIdGenerator;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\DummyCallback;
use Stubs\NestedWorkflow;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;
use Stubs\TestJob5;
use Stubs\TestJob6;
use Stubs\TestWorkflow;
use Stubs\WorkflowWithJob;
use function Pest\Laravel\assertDatabaseHas;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__before_connect_callback'] = 0;
});

it('creates a workflow', function (): void {
    createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), dependencies: [TestJob1::class])
        ->build();

    assertDatabaseHas('workflows', [
        'job_count' => 2,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => \json_encode([]),
    ]);
});

it('returns the workflow\'s initial batch of jobs', function (): void {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    [$workflow, $initialBatch] = createDefinition()
        ->addJob($job1)
        ->addJob($job2)
        ->addJob(new TestJob3(), dependencies: [TestJob1::class])
        ->build();

    assertEquals([$job1, $job2], $initialBatch);
});

it('returns the workflow', function (): void {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    [$workflow, $initialBatch] = createDefinition()
        ->addJob($job1)
        ->addJob($job2)
        ->addJob(new TestJob3(), dependencies: [TestJob1::class])
        ->build();

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('sets a reference to the workflow on each job', function (): void {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    createDefinition()
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    $workflowId = Workflow::first()->id;
    assertEquals($workflowId, $testJob1->workflowId);
    assertEquals($workflowId, $testJob2->workflowId);
});

it('sets the job dependencies on the job instances', function (): void {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $testJob3 = new TestJob2();

    createDefinition()
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class], id: '::job-2-id::')
        ->addJob($testJob3, dependencies: ['::job-2-id::'])
        ->build();

    assertEquals([TestJob1::class], $testJob2->dependencies);
    assertEquals([], $testJob1->dependencies);
    assertEquals(['::job-2-id::'], $testJob3->dependencies);
});

it('sets the jobId on the job instance if explicitly provided', function (): void {
    $testJob = new TestJob1();

    createDefinition()
        ->addJob($testJob, id: '::job-id::')
        ->build();

    expect($testJob)->jobId->toBe('::job-id::');
});

it('sets the dependants of a job', function (): void {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    createDefinition()
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    assertEquals([$testJob2->stepId], $testJob1->dependantJobs);
    assertEquals([], $testJob2->dependantJobs);
});

it('saves the workflow steps to the database', function (): void {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();
    $testJob3 = new TestJob3();

    [$workflow, $initialJobs] = createDefinition()
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->addGatedJob($testJob3)
        ->build();

    assertDatabaseHas('workflow_jobs', [
        'workflow_id' => $workflow->id,
        'gated' => false,
        'job' => \serialize($testJob1),
    ]);
    assertDatabaseHas('workflow_jobs', [
        'workflow_id' => $workflow->id,
        'gated' => false,
        'job' => \serialize($testJob2),
    ]);
    assertDatabaseHas('workflow_jobs', [
        'workflow_id' => $workflow->id,
        'gated' => true,
        'job' => \serialize($testJob3),
    ]);
});

it('saves the list of edges for each job', function (): void {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    [$workflow, $initialBatch] = createDefinition()
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class])
        ->build();

    $jobs = $workflow->jobs;
    assertEquals(
        [$testJob2->stepId],
        $jobs->firstWhere('uuid', $testJob1->stepId)->edges,
    );
    assertEquals(
        [],
        $jobs->firstWhere('uuid', $testJob2->stepId)->edges,
    );
});

it('uses the class name as the jobs name if no name was provided', function (): void {
    createDefinition()
        ->addJob(new TestJob1())
        ->build();

    assertDatabaseHas('workflow_jobs', ['name' => TestJob1::class]);
});

it('uses the nice name if it was provided', function (): void {
    createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), dependencies: [TestJob1::class], name: '::job-name::')
        ->build();

    assertDatabaseHas('workflow_jobs', ['name' => '::job-name::']);
});

it('creates workflow step records that use the jobs uuid', function (): void {
    $testJob1 = new TestJob1();
    $testJob2 = new TestJob2();

    createDefinition()
        ->addJob($testJob1)
        ->addJob($testJob2, dependencies: [TestJob1::class], name: '::job-name::')
        ->build();

    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob1->stepId]);
    assertDatabaseHas('workflow_jobs', ['uuid' => $testJob2->stepId]);
});

it('creates a workflow with the provided name', function (): void {
    [$workflow, $initialBatch] = createDefinition('::workflow-name::')
        ->addJob(new TestJob1())
        ->build();

    assertEquals('::workflow-name::', $workflow->name);
});

it('allows configuration of a then callback', function (): void {
    $callback = function (Workflow $wf): void {
        echo 'derp';
    };
    [$workflow, $initialBatch] = createDefinition('::name::')
        ->then($callback)
        ->build();

    assertEquals($workflow->then_callback, \serialize(new SerializableClosure($callback)));
});

it('allows configuration of an invokable class as then callback', function (): void {
    $callback = new DummyCallback();

    [$workflow, $initialBatch] = createDefinition('::name::')
        ->then($callback)
        ->build();

    assertEquals($workflow->then_callback, \serialize($callback));
});

it('allows configuration of a catch callback', function (): void {
    $callback = function (Workflow $wf): void {
        echo 'derp';
    };
    [$workflow, $initialBatch] = createDefinition('::name::')
        ->catch($callback)
        ->build();

    assertEquals($workflow->catch_callback, \serialize(new SerializableClosure($callback)));
});

it('allows configuration of an invokable class as catch callback', function (): void {
    $callback = new DummyCallback();

    [$workflow, $initialBatch] = createDefinition('::name::')
        ->catch($callback)
        ->build();

    assertEquals($workflow->catch_callback, \serialize($callback));
});

it('can add a job with a delay', function ($delay): void {
    Carbon::setTestNow(now());

    $workflow1 = createDefinition('::name-1::')
        ->addJob(new TestJob1(), delay: $delay);
    $workflow2 = createDefinition('::name-2::')
        ->addJob(new TestJob2(), delay: $delay);

    assertTrue($workflow1->hasJobWithDelay(TestJob1::class, $delay));
    assertTrue($workflow2->hasJobWithDelay(TestJob2::class, $delay));
})->with('delay provider');

it('allows adding a job as a closure', function (): void {
    $definition = createDefinition()
        ->addJob(
            fn () => 'foo',
            [],
            '::job-name::',
            500,
            '::job-id::',
        );

    expect($definition->hasJob('::job-id::', [], 500))->toBeTrue();
});

it('can add a gated job', function (): void {
    $definition = createDefinition()
        ->addGatedJob(
            new TestJob1(),
            [],
            '::name::',
            '::id::',
        );

    expect($definition)
        ->hasJob('::id::', dependencies: [], delay: null, gated: true)
        ->toBeTrue();
});

it('throws an exception when adding a closure job without an explicit id', function (): void {
    createDefinition()->addJob(fn () => 'foo', id: null);
})->throws(
    InvalidJobException::class,
    'Closure-based jobs must have an explicit id',
);

it('throws an exception when adding a job that does not implement WorkflowStepInterface or use the WorkflowStep trait', function (): void {
    createDefinition()->addJob(new NonWorkflowJob());
})->throws(
    InvalidJobException::class,
    'Job [Stubs\NonWorkflowJob] does not implement WorkflowStepInterface or use the WorkflowStep trait',
);

it('returns true if job is part of the workflow', function (): void {
    $definition = createDefinition('::name::')
        ->addJob(new TestJob1());

    assertTrue($definition->hasJob(TestJob1::class));
});

it('returns false if job is not part of the workflow', function (): void {
    $definition = createDefinition('::name::')
        ->addJob(new TestJob2());

    assertFalse($definition->hasJob(TestJob1::class));
});

it('returns true if job exists with the correct dependencies', function (): void {
    $definition = createDefinition('::name::')
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), dependencies: [TestJob1::class]);

    assertTrue($definition->hasJob(TestJob2::class, [TestJob1::class]));
});

it('returns false if job exists, but with incorrect dependencies', function (): void {
    $definition = createDefinition('::name::')
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), dependencies: [TestJob2::class]);

    assertFalse($definition->hasJob(TestJob3::class, [TestJob1::class]));
});

it('returns false if job exists without delay', function (): void {
    Carbon::setTestNow(now());

    $definition = createDefinition('::name::')
        ->addJob(new TestJob1());

    assertFalse($definition->hasJob(TestJob1::class, [], now()->addDay()));
});

it('returns true if job exists with correct delay', function ($delay): void {
    Carbon::setTestNow(now());

    $definition = createDefinition('::name::')
        ->addJob(new TestJob1(), delay: $delay);

    assertTrue($definition->hasJob(TestJob1::class, [], $delay));
})->with('delay provider');

dataset('delay provider', [
    'carbon date' => [now()->addHour()],
    'integer' => [2000],
    'date interval' => [new DateInterval('P14D')],
]);

it('returns true if gated job is part of the workflow', function (): void {
    $definition = createDefinition()
        ->addGatedJob(new TestJob1());

    expect($definition)
        ->hasJob(TestJob1::class, gated: true)->toBeTrue();
});

it('returns false if job exists for id but without gate', function (): void {
    $definition = createDefinition()
        ->addJob(new TestJob1());

    expect($definition)
        ->hasJob(TestJob1::class, gated: true)->toBeFalse();
});

it('calls the before create hook before saving the workflow if provided', function (): void {
    $callback = function (Workflow $workflow): void {
        $workflow->name = '::new-name::';
    };

    [$workflow, $initialBatch] = createDefinition('::old-name::')
        ->addJob(new TestJob1(), dependencies: [])
        ->build($callback);

    assertEquals('::new-name::', $workflow->name);
});

it('calls the before connecting hook before adding a nested workflow', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition('::name::')
                ->addJob(new TestJob2());
        }

        public function beforeNesting(array $jobs): void
        {
            ++$_SERVER['__before_connect_callback'];
        }
    };

    createDefinition('::name::')
        ->addWorkflow(new $workflow(), []);

    assertEquals(1, $_SERVER['__before_connect_callback']);
});

it('can add another workflow', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return $this->define('::name::')
                ->addJob(new TestJob4())
                ->addJob(new TestJob5())
                ->addJob(new TestJob6(), dependencies: [TestJob4::class]);
        }
    };
    $definition = createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2())
        ->addJob(new TestJob3(), dependencies: [TestJob1::class])
        ->addWorkflow($workflow, dependencies: [TestJob1::class]);

    assertTrue($definition->hasJobWithDependencies($workflow::class . '.' . TestJob4::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies($workflow::class . '.' . TestJob5::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies($workflow::class . '.' . TestJob6::class, [$workflow::class . '.' . TestJob4::class]));
});

it('adding another workflow namespaces the nested workflow\'s job ids', function (): void {
    $definition = createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addWorkflow(new NestedWorkflow());

    assertTrue($definition->hasJob(NestedWorkflow::class . '.' . TestJob1::class));
    assertTrue($definition->hasJob(TestJob1::class));
    assertTrue($definition->hasJobWithDependencies(TestJob2::class, [TestJob1::class]));
});

it('adding another workflow updates the job id on nested job instances', function (): void {
    $definition = createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob2(), [TestJob1::class])
        ->addWorkflow(new NestedWorkflow($job = new TestJob1()));

    assertEquals(NestedWorkflow::class . '.' . TestJob1::class, $job->jobId);
});

it('allows multiple instances of the same job with explicit ids', function (): void {
    $definition = createDefinition()
        ->addJob(new TestJob1(), id: '::id-1::')
        ->addJob(new TestJob1(), id: '::id-2::');

    assertTrue($definition->hasJob('::id-1::'));
    assertTrue($definition->hasJob('::id-2::'));
});

it('can allows FQCN and explicit id when declaring dependencies', function (): void {
    $definition = createDefinition()
        ->addJob(new TestJob1())
        ->addJob(new TestJob1(), id: '::id::')
        ->addJob(new TestJob2(), dependencies: [TestJob1::class])
        ->addJob(new TestJob3(), dependencies: ['::id::']);

    assertTrue($definition->hasJobWithDependencies(TestJob2::class, [TestJob1::class]));
    assertTrue($definition->hasJobWithDependencies(TestJob3::class, ['::id::']));
});

it('can add multiple instances of the same workflow if they have different ids', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition('::name::')
                ->addJob(new TestJob2(), id: '::job-2-id::');
        }
    };
    $definition = createDefinition()
        ->addJob(new TestJob1(), id: '::job-1-id::')
        ->addWorkflow($workflow, dependencies: ['::job-1-id::'], id: '::workflow-1-id::')
        ->addWorkflow($workflow, dependencies: ['::job-1-id::'], id: '::workflow-2-id::');

    assertTrue($definition->hasJobWithDependencies('::workflow-1-id::.::job-2-id::', ['::job-1-id::']));
    assertTrue($definition->hasJobWithDependencies('::workflow-2-id::.::job-2-id::', ['::job-1-id::']));
});

it('can check if a workflow contains a nested workflow', function (array $configureWorkflow, ?array $dependencies, bool $expected): void {
    $definition = createDefinition();

    // Look, I have neither the time nor the energy to figure out how Pest deals with closures
    // inside a data provider. So I'm just going to wrap the callback in an array to circumvent
    // Pest's default behavior ¯\_(ツ)_/¯
    $configureWorkflow[0]($definition);

    assertEquals($expected, $definition->hasWorkflow(NestedWorkflow::class, $dependencies));
})->with([
    'has workflow, ignore dependencies' => [
        'configureWorkflow' => [function (WorkflowDefinition $definition): void {
            $definition->addWorkflow(new NestedWorkflow());
        }],
        'dependencies' => null,
        'expected' => true,
    ],
    'does not have workflow, ignore dependencies' => [
        'configureWorkflow' => [function (WorkflowDefinition $definition): void {
        }],
        'dependencies' => null,
        'expected' => false,
    ],
    'has workflow, incorrect dependencies' => [
        'configureWorkflow' => [function (WorkflowDefinition $definition): void {
            $definition
                ->addWorkflow(new NestedWorkflow());
        }],
        'dependencies' => [TestJob1::class],
        'expected' => false,
    ],
    'has workflow, correct dependencies' => [
        'configureWorkflow' => [function (WorkflowDefinition $definition): void {
            $definition
                ->addJob(new TestJob1())
                ->addWorkflow(new NestedWorkflow(), [TestJob1::class]);
        }],
        'dependencies' => [TestJob1::class],
        'expected' => true,
    ],
]);

it('can configure the connection on all jobs', function (): void {
    $definition = createDefinition()
        ->allOnConnection('::connection::')
        ->addJob((new TestJob1())->onConnection('::job-1-connection::'))
        ->addJob((new TestJob2())->onConnection('::job-2-connection::'))
        ->addJob(new TestJob3(), [TestJob1::class]);

    $definition->build();

    foreach ($definition->jobs() as $job) {
        expect($job->getConnection())->toBe('::connection::');
    }
});

it('does not override job connections if no explicit connection was provided', function (): void {
    $definition = createDefinition()
        ->addJob((new TestJob1())->onConnection('::job-1-connection::'))
        ->addJob((new TestJob2())->onConnection('::job-2-connection::'))
        ->addJob(new TestJob3(), [TestJob1::class]);

    $definition->build();

    foreach ($definition->jobs() as $job) {
        expect($job->getConnection())->match($job::class, [
            TestJob1::class => '::job-1-connection::',
            TestJob2::class => '::job-2-connection::',
            TestJob3::class => null,
        ]);
    }
});

it('can configure the queue on all jobs', function (): void {
    $definition = createDefinition()
        ->allOnQueue('::queue::')
        ->addJob((new TestJob1())->onQueue('::job-1-queue::'))
        ->addJob((new TestJob2())->onQueue('::job-2-queue::'))
        ->addJob(new TestJob3(), [TestJob1::class]);

    $definition->build();

    foreach ($definition->jobs() as $job) {
        expect($job->getQueue())->toBe('::queue::');
    }
});

it('does not override the job queues if no explicit queue was provided', function (): void {
    $definition = createDefinition()
        ->addJob((new TestJob1())->onQueue('::job-1-queue::'))
        ->addJob((new TestJob2())->onQueue('::job-2-queue::'))
        ->addJob(new TestJob3(), [TestJob1::class]);

    $definition->build();

    foreach ($definition->jobs() as $job) {
        expect($job->getQueue())->match($job::class, [
            TestJob1::class => '::job-1-queue::',
            TestJob2::class => '::job-2-queue::',
            TestJob3::class => null,
        ]);
    }
});

it('fires an event after a job was added', function (): void {
    Event::fake([JobAdded::class]);
    $job = new TestJob1();

    $definition = createDefinition()
        ->addJob($job, [], '::job-name::', 300, '::job-id::');

    Event::assertDispatched(JobAdded::class, function (JobAdded $event) use ($definition, $job): bool {
        return $event->definition === $definition
            && $event->job === $job;
    });
});

it('fires an event after a workflow was added', function (): void {
    Event::fake([WorkflowAdded::class]);

    $nestedWorkflow = new WorkflowWithJob();
    $definition = createDefinition()->addWorkflow($nestedWorkflow, [], '::id::');

    Event::assertDispatched(
        WorkflowAdded::class,
        function (WorkflowAdded $event) use ($definition, $nestedWorkflow) {
            return $event->parentDefinition === $definition
                && $event->nestedDefinition->workflow() === $nestedWorkflow
                && '::id::' === $event->workflowID;
        },
    );
});

it('fires an event before a workflow gets saved', function (): void {
    Event::fake([WorkflowCreating::class]);
    $workflow = new TestWorkflow();
    $definition = createDefinition(workflow: $workflow);

    [$model, $initialJobs] = $definition->build();

    Event::assertDispatched(
        WorkflowCreating::class,
        function (WorkflowCreating $event) use ($definition, $model): bool {
            return $event->definition === $definition
                && $event->model === $model;
        },
    );
});

it('applies the same dependencies to all jobs added with `each`', function (): void {
    $jobs = [TestJob1::class, TestJob1::class, TestJob1::class];

    $definition = createDefinition()
        ->addJob(new TestJob2())
        ->each(
            $jobs,
            fn (string $job) => new $job(),
            dependencies: [TestJob2::class],
        );

    expect(
        $definition->hasJobWithDependencies(TestJob1::class . '_1', [TestJob2::class]),
    )->toBeTrue();
    expect(
        $definition->hasJobWithDependencies(TestJob1::class . '_2', [TestJob2::class]),
    )->toBeTrue();
    expect(
        $definition->hasJobWithDependencies(TestJob1::class . '_3', [TestJob2::class]),
    )->toBeTrue();
});

it('enumerates the id when adding jobs with `each`', function (): void {
    $jobs = [TestJob1::class, TestJob1::class, TestJob1::class];

    $definition = createDefinition()
        ->each(
            $jobs,
            fn (string $job) => new $job(),
            id: 'test_job',
        );

    expect($definition->hasJob('test_job_1'))->toBeTrue();
    expect($definition->hasJob('test_job_2'))->toBeTrue();
    expect($definition->hasJob('test_job_3'))->toBeTrue();
});

it('resolves each job\'s id via the step generator when no explicit id is provided and enumerates it', function (): void {
    app()->instance(StepIdGenerator::class, new FakeIDGenerator('::job-id::'));

    $jobs = [TestJob1::class, TestJob1::class, TestJob1::class];

    $definition = createDefinition()
        ->each($jobs, fn (string $job) => new $job());

    expect($definition->hasJob('::job-id::_1'))->toBeTrue();
    expect($definition->hasJob('::job-id::_2'))->toBeTrue();
    expect($definition->hasJob('::job-id::_3'))->toBeTrue();
});

it('applies the same name to all jobs added with `each`', function (): void {
    $jobs = [TestJob1::class, TestJob1::class, TestJob1::class];

    $workflowJobs = createDefinition()
        ->each($jobs, fn (string $job) => new $job(), name: '::job-name::')
        ->jobs();

    expect(Arr::pluck($workflowJobs, 'name'))
        ->each(fn ($name) => $name->toBe('::job-name::'));
});

it('applies the same delay to all jobs added with `each`', function (): void {
    $jobs = [TestJob1::class, TestJob1::class, TestJob1::class];

    $definition = createDefinition()
        ->each($jobs, fn (string $job) => new $job(), delay: 5);

    expect($definition->hasJobWithDelay(TestJob1::class . '_1', 5))->toBeTrue();
    expect($definition->hasJobWithDelay(TestJob1::class . '_2', 5))->toBeTrue();
    expect($definition->hasJobWithDelay(TestJob1::class . '_3', 5))->toBeTrue();
});

it('creates a group for all jobs added with `each` when an explicit id was provided', function (): void {
    $jobs = [new TestJob1(), new TestJob1(), new TestJob1()];

    $definition = createDefinition()
        ->each($jobs, fn (TestJob1 $job) => $job, id: '::job-id::');

    $group = $definition->graph()->getGroup('::job-id::');
    expect($group)->not()->toBeNull();
    expect($group[0]->getJob())->toBe($jobs[0]);
    expect($group[1]->getJob())->toBe($jobs[1]);
    expect($group[2]->getJob())->toBe($jobs[2]);
});

it('does not create a group when adding jobs with `each` if no explicit id was provided', function (): void {
    $jobs = [new TestJob1(), new TestJob1(), new TestJob1()];

    $definition = createDefinition()->each($jobs, fn (TestJob1 $job) => $job);

    expect($definition->graph()->getGroups())->toBeEmpty();
});

it('can add workflows using `each`', function (): void {
    $jobs = [new TestJob1(), new TestJob1(), new TestJob1()];

    $definition = createDefinition()->each(
        $jobs,
        fn (TestJob1 $job) => new NestedWorkflow($job),
        id: '::workflow-id::',
    );

    expect($definition->hasWorkflow('::workflow-id::_1'))->toBeTrue();
    expect($definition->hasWorkflow('::workflow-id::_2'))->toBeTrue();
    expect($definition->hasWorkflow('::workflow-id::_3'))->toBeTrue();

    $group = $definition->graph()->getGroup('::workflow-id::');
    expect($group[0]->getJob())->toBe($jobs[0]);
    expect($group[1]->getJob())->toBe($jobs[1]);
    expect($group[2]->getJob())->toBe($jobs[2]);
});
