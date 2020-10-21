<?php declare(strict_types=1);

use Stubs\TestAbstractWorkflow;
use Sassnowski\Venture\Facades\Workflow;

uses(TestCase::class);

it('can be started', function () {
    Workflow::fake();

    TestAbstractWorkflow::start();

    Workflow::assertStarted(TestAbstractWorkflow::class);
});
