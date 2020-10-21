<?php declare(strict_types=1);

use Stubs\TestWorkflow;
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

    assertFalse($managerFake->hasStarted(TestWorkflow::class));
    $managerFake->startWorkflow(new TestWorkflow());

    assertTrue($managerFake->hasStarted(TestWorkflow::class));
});

it('stores the workflow to the database', function () {
    $managerFake = new WorkflowManagerFake();

    $workflow = $managerFake->startWorkflow(new TestWorkflow());

    assertTrue($workflow->exists);
    assertTrue($workflow->wasRecentlyCreated);
});

it('passes if a workflow was started', function () {
    $managerFake = new WorkflowManagerFake();

    $managerFake->startWorkflow(new TestWorkflow());

    $managerFake->assertStarted(TestWorkflow::class);
});

it('fails if the expected workflow was not started', function () {
    $managerFake = new WorkflowManagerFake();
    $expectedWorkflow = TestWorkflow::class;

    test()->expectException(AssertionFailedError::class);
    test()->expectExceptionMessage("The expected workflow [{$expectedWorkflow}] was not started");

    $managerFake->assertStarted($expectedWorkflow);
});
