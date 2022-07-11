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

use PHPUnit\Framework\AssertionFailedError;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Facades\Workflow;
use Sassnowski\Venture\Manager\WorkflowManagerInterface;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestAbstractWorkflow;
use Stubs\WorkflowWithParameter;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

beforeEach(function (): void {
    $this->managerFake = Workflow::fake();
});

it('implements the correct interface', function (): void {
    assertInstanceOf(WorkflowManagerInterface::class, $this->managerFake);
});

it('records all started workflows', function (): void {
    assertFalse($this->managerFake->hasStarted(TestAbstractWorkflow::class));
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    assertTrue($this->managerFake->hasStarted(TestAbstractWorkflow::class));
});

it('stores the workflow to the database', function (): void {
    $workflow = $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('passes if a workflow was started', function (): void {
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    $this->managerFake->assertStarted(TestAbstractWorkflow::class);
});

it('fails if the expected workflow was not started', function (): void {
    $expectedWorkflow = TestAbstractWorkflow::class;

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $this->managerFake->assertStarted($expectedWorkflow);
});

it('fails if the provided callback returns false', function (): void {
    $expectedWorkflow = WorkflowWithParameter::class;

    $this->managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $this->managerFake->assertStarted($expectedWorkflow, function (WorkflowWithParameter $workflow) {
        return '::other-input::' === $workflow->something;
    });
});

it('passes if the workflow was started and the callback returns true', function (): void {
    $expectedWorkflow = WorkflowWithParameter::class;

    $this->managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    $this->managerFake->assertStarted($expectedWorkflow, function (WorkflowWithParameter $workflow) {
        return '::input::' === $workflow->something;
    });
});

it('passes if the workflow was not started', function (): void {
    $expectedWorkflow = TestAbstractWorkflow::class;

    $this->managerFake->assertNotStarted($expectedWorkflow);
});

it('fails if the workflow was started', function (): void {
    $workflow = TestAbstractWorkflow::class;

    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The unexpected [{$workflow}] workflow was started");

    $this->managerFake->assertNotStarted($workflow);
});

it('passes if a workflow was started, but the callback returns false', function (): void {
    $workflow = WorkflowWithParameter::class;

    $this->managerFake->startWorkflow(new WorkflowWithParameter('::some-parameter::'));

    $this->managerFake->assertNotStarted($workflow, function (WorkflowWithParameter $workflow) {
        return '::other-parameter::' === $workflow->something;
    });
});

it('runs the beforeCreate hook', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition();
            ('::name::');
        }

        public function beforeCreate(Sassnowski\Venture\Models\Workflow $workflow): void
        {
            $workflow->name = '::new-name::';
        }
    };

    $workflow = $this->managerFake->startWorkflow($workflow);

    assertEquals('::new-name::', $workflow->name);
});
