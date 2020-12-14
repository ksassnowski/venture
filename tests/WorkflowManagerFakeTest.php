<?php declare(strict_types=1);

use Stubs\TestAbstractWorkflow;
use Stubs\WorkflowWithParameter;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Facades\Workflow;
use function PHPUnit\Framework\assertTrue;
use Sassnowski\Venture\WorkflowDefinition;
use function PHPUnit\Framework\assertFalse;
use PHPUnit\Framework\AssertionFailedError;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use Sassnowski\Venture\Manager\WorkflowManagerInterface;

uses(TestCase::class);

beforeEach(function () {
    $this->managerFake = Workflow::fake();
});

it('implements the correct interface', function () {
    assertInstanceOf(WorkflowManagerInterface::class, $this->managerFake);
});

it('records all started workflows', function () {
    assertFalse($this->managerFake->hasStarted(TestAbstractWorkflow::class));
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    assertTrue($this->managerFake->hasStarted(TestAbstractWorkflow::class));
});

it('stores the workflow to the database', function () {
    $workflow = $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('passes if a workflow was started', function () {
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    $this->managerFake->assertStarted(TestAbstractWorkflow::class);
});

it('fails if the expected workflow was not started', function () {
    $expectedWorkflow = TestAbstractWorkflow::class;

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $this->managerFake->assertStarted($expectedWorkflow);
});

it('fails if the provided callback returns false', function () {
    $expectedWorkflow = WorkflowWithParameter::class;

    $this->managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $this->managerFake->assertStarted($expectedWorkflow, function (WorkflowWithParameter $workflow) {
        return $workflow->something === '::other-input::';
    });
});

it('passes if the workflow was started and the callback returns true', function () {
    $expectedWorkflow = WorkflowWithParameter::class;

    $this->managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    $this->managerFake->assertStarted($expectedWorkflow, function (WorkflowWithParameter $workflow) {
        return $workflow->something === '::input::';
    });
});

it('passes if the workflow was not started', function () {
    $expectedWorkflow = TestAbstractWorkflow::class;

    $this->managerFake->assertNotStarted($expectedWorkflow);
});

it('fails if the workflow was started', function () {
    $workflow = TestAbstractWorkflow::class;

    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The unexpected [{$workflow}] workflow was started");

    $this->managerFake->assertNotStarted($workflow);
});

it('passes if a workflow was started, but the callback returns false', function () {
    $workflow = WorkflowWithParameter::class;

    $this->managerFake->startWorkflow(new WorkflowWithParameter('::some-parameter::'));

    $this->managerFake->assertNotStarted($workflow, function (WorkflowWithParameter $workflow) {
        return $workflow->something === '::other-parameter::';
    });
});

it('runs the beforeCreate hook', function () {
    $workflow = new class extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return Workflow::define('::name::');
        }

        public function beforeCreate(\Sassnowski\Venture\Models\Workflow $workflow): void
        {
            $workflow->name = '::new-name::';
        }
    };

    $workflow = $this->managerFake->startWorkflow($workflow);

    assertEquals('::new-name::', $workflow->name);
});
