<?php declare(strict_types=1);

use Sassnowski\Venture\Models\Workflow as WorkflowModel;
use Stubs\TestAbstractWorkflow;
use Stubs\WorkflowWithParameter;
use Sassnowski\Venture\Facades\Workflow;
use function PHPUnit\Framework\assertInstanceOf;

uses(TestCase::class);

it('can be started', function () {
    Workflow::fake();

    $workflow = TestAbstractWorkflow::start();

    Workflow::assertStarted(TestAbstractWorkflow::class);
    assertInstanceOf(WorkflowModel::class, $workflow);
});

it('passes the parameters to the constructor of the workflow', function () {
    Workflow::fake();

    WorkflowWithParameter::start('::param::');

    Workflow::assertStarted(WorkflowWithParameter::class, function (WorkflowWithParameter $workflow) {
        return $workflow->something === '::param::';
    });
});
