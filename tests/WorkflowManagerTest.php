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

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Dispatcher\FakeDispatcher;
use Sassnowski\Venture\Events\WorkflowStarted;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestWorkflow;

uses(TestCase::class);

beforeEach(function (): void {
    Bus::fake();
    $this->dispatcher = new FakeDispatcher();
    $this->manager = new WorkflowManager($this->dispatcher);
});

it('creates a new workflow definition with the provided name', function (): void {
    $workflow = new TestWorkflow();
    $definition = $this->manager->define($workflow, '::name::');

    expect($definition)->toBeInstanceOf(WorkflowDefinition::class);
    expect($definition)
        ->name()->toBe('::name::')
        ->workflow()->toBe($workflow);
});

it('dispatches all initial jobs of the workflow', function (): void {
    $definition = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition('::name::')
                ->addJob(new TestJob1())
                ->addJob(new TestJob2())
                ->addJob(new TestJob3(), [TestJob1::class]);
        }
    };

    $this->manager->startWorkflow($definition);

    $this->dispatcher->assertJobWasDispatched(TestJob1::class);
    $this->dispatcher->assertJobWasDispatched(TestJob2::class);
    $this->dispatcher->assertJobWasNotDispatched(TestJob3::class);
});

it('returns the created workflow', function (): void {
    $definition = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition('::name::')
                ->addJob(new TestJob1());
        }
    };

    $workflow = $this->manager->startWorkflow($definition);

    expect($workflow)->toBeInstanceOf(Workflow::class);
    expect($workflow)
        ->exists->toBeTrue()
        ->wasRecentlyCreated->toBeTrue();
});

it('applies the before create hook if it exists', function (): void {
    $definition = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition('::old-name::')
                ->addJob(new TestJob1());
        }

        public function beforeCreate(Workflow $workflow): void
        {
            $workflow->name = '::new-name::';
        }
    };

    $workflow = $this->manager->startWorkflow($definition);

    expect($workflow->name)->toBe('::new-name::');
});

it('fires an event after a workflow was started', function (): void {
    Event::fake([WorkflowStarted::class]);
    $workflow = new TestWorkflow();

    $model = $this->manager->startWorkflow($workflow);

    Event::assertDispatched(
        WorkflowStarted::class,
        function (WorkflowStarted $event) use ($model, $workflow): bool {
            return $event->model === $model
                && $event->workflow === $workflow;
        },
    );
});

it('sets the provided connection on all jobs', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return $this->define('::name::')
                ->addJob((new TestJob1())->onConnection('::connection-1::'))
                ->addJob((new TestJob2())->onConnection('::connection-2::'));
        }
    };

    $this->manager->startWorkflow($workflow, '::new-connection::');

    $this->dispatcher->assertJobWasDispatched(TestJob1::class, '::new-connection::');
    $this->dispatcher->assertJobWasDispatched(TestJob2::class, '::new-connection::');
});

it('overrides the global workflow connection if an explicit connection was provided', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return $this->define('::name::')
                ->allOnConnection('::old-connection::')
                ->addJob(new TestJob1())
                ->addJob(new TestJob2());
        }
    };

    $this->manager->startWorkflow($workflow, '::new-connection::');

    $this->dispatcher->assertJobWasDispatched(TestJob1::class, '::new-connection::');
    $this->dispatcher->assertJobWasDispatched(TestJob2::class, '::new-connection::');
});

it('does not change the job connections if no explicit connection was provided', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return $this->define('::name::')
                ->addJob((new TestJob1())->onConnection('::connection-1::'))
                ->addJob((new TestJob2())->onConnection('::connection-2::'));
        }
    };

    $this->manager->startWorkflow($workflow);

    $this->dispatcher->assertJobWasDispatched(TestJob1::class, '::connection-1::');
    $this->dispatcher->assertJobWasDispatched(TestJob2::class, '::connection-2::');
});
