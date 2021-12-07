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

use Illuminate\Support\Facades\Bus;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\Facades\Workflow as WorkflowFacade;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    $this->dispatcherSpy = Bus::fake();
    $this->manager = new WorkflowManager(
        $this->dispatcherSpy,
        new ClassNameStepIdGenerator(),
    );
});

it('creates a new workflow definition with the provided name', function (): void {
    $definition = $this->manager->define('::name::');

    assertInstanceOf(WorkflowDefinition::class, $definition);
    assertEquals('::name::', $definition->name());
});

it('starts a workflow by dispatching all jobs without dependencies', function (): void {
    $definition = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob1())
                ->addJob(new TestJob2())
                ->addJob(new TestJob3(), [TestJob1::class]);
        }
    };

    $this->manager->startWorkflow($definition);

    Bus::assertDispatchedTimes(TestJob1::class, 1);
    Bus::assertDispatchedTimes(TestJob2::class, 1);
    Bus::assertNotDispatched(TestJob3::class);
});

it('returns the created workflow', function (): void {
    $definition = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return WorkflowFacade::define('::name::')
                ->addJob(new TestJob1());
        }
    };

    $workflow = $this->manager->startWorkflow($definition);

    assertInstanceOf(Workflow::class, $workflow);
    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('applies the before create hook if it exists', function (): void {
    $definition = new class() extends AbstractWorkflow {
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

    $workflow = $this->manager->startWorkflow($definition);

    assertEquals($workflow->name, '::new-name::');
});
