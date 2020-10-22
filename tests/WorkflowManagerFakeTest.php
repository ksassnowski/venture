<?php declare(strict_types=1);

use Stubs\TestAbstractWorkflow;
use Stubs\WorkflowWithParameter;
use Sassnowski\Venture\Facades\Workflow;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertFalse;
use PHPUnit\Framework\AssertionFailedError;
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
