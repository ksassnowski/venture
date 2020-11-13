<?php declare(strict_types=1);

use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Illuminate\Support\Facades\Bus;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\AbstractWorkflow;
use function PHPUnit\Framework\assertTrue;
use Sassnowski\Venture\WorkflowDefinition;
use function PHPUnit\Framework\assertEquals;
use Sassnowski\Venture\Manager\WorkflowManager;
use function PHPUnit\Framework\assertInstanceOf;
use Sassnowski\Venture\Facades\Workflow as WorkflowFacade;

uses(TestCase::class);

beforeEach(function () {
    $this->dispatcherSpy = Bus::fake();
    $this->manager = new WorkflowManager($this->dispatcherSpy);
});

it('creates a new workflow definition with the provided name', function () {
    $definition = $this->manager->define('::name::');

    assertInstanceOf(WorkflowDefinition::class, $definition);
    assertEquals('::name::', $definition->name());
});

it('starts a workflow by dispatching all jobs without dependencies', function () {
    $definition = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob1())
                ->addJob(new TestJob2())
                ->addJob(new TestJob3(), [TestJob1::class]);
        }
    };
    $manager = new WorkflowManager($this->dispatcherSpy);

    $manager->startWorkflow($definition);

    Bus::assertDispatchedTimes(TestJob1::class, 1);
    Bus::assertDispatchedTimes(TestJob2::class, 1);
    Bus::assertNotDispatched(TestJob3::class);
});

it('returns the created workflow', function () {
    $definition = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob1());
        }
    };
    $manager = new WorkflowManager($this->dispatcherSpy);

    $workflow = $manager->startWorkflow($definition);

    assertInstanceOf(Workflow::class, $workflow);
    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('applies the before create hook if it exists', function () {
    $definition = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::old-name::')
                ->addJob(new TestJob1());
        }

        public function beforeCreate(Workflow $workflow): void
        {
            $workflow->name = '::new-name::';
        }
    };
    $manager = new WorkflowManager($this->dispatcherSpy);

    $workflow = $manager->startWorkflow($definition);

    assertEquals($workflow->name, '::new-name::');
});
