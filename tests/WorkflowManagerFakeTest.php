<?php declare(strict_types=1);

use Stubs\TestAbstractWorkflow;
use Stubs\WorkflowWithParameter;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertFalse;
use PHPUnit\Framework\AssertionFailedError;
use function PHPUnit\Framework\assertInstanceOf;
use Sassnowski\Venture\Manager\WorkflowManagerFake;
use Sassnowski\Venture\Manager\WorkflowManagerInterface;

uses(TestCase::class);

it('implements the correct interface', function () {
    assertInstanceOf(WorkflowManagerInterface::class, new WorkflowManagerFake());
});

it('records all started workflows', function () {
    $managerFake = new WorkflowManagerFake();

    assertFalse($managerFake->hasStarted(TestAbstractWorkflow::class));
    $managerFake->startWorkflow(new TestAbstractWorkflow());

    assertTrue($managerFake->hasStarted(TestAbstractWorkflow::class));
});

it('stores the workflow to the database', function () {
    $managerFake = new WorkflowManagerFake();

    $workflow = $managerFake->startWorkflow(new TestAbstractWorkflow());

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('passes if a workflow was started', function () {
    $managerFake = new WorkflowManagerFake();

    $managerFake->startWorkflow(new TestAbstractWorkflow());

    $managerFake->assertStarted(TestAbstractWorkflow::class);
});

it('fails if the expected workflow was not started', function () {
    $managerFake = new WorkflowManagerFake();
    $expectedWorkflow = TestAbstractWorkflow::class;

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $managerFake->assertStarted($expectedWorkflow);
});

it('fails if the provided callback returns false', function () {
    $managerFake = new WorkflowManagerFake();
    $expectedWorkflow = WorkflowWithParameter::class;

    $managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $managerFake->assertStarted($expectedWorkflow, function (WorkflowWithParameter $workflow) {
        return $workflow->something === '::other-input::';
    });
});

it('passes if the workflow was started and the callback returns true', function () {
    $managerFake = new WorkflowManagerFake();
    $expectedWorkflow = WorkflowWithParameter::class;

    $managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    $managerFake->assertStarted($expectedWorkflow, function (WorkflowWithParameter $workflow) {
        return $workflow->something === '::input::';
    });
});
