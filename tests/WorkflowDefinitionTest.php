<?php declare(strict_types=1);

use Stubs\TestWorkflow;
use Sassnowski\Venture\Facades\Workflow;

uses(TestCase::class);

it('can be started', function () {
    Workflow::fake();

    TestWorkflow::start();

    Workflow::assertStarted(TestWorkflow::class);
});
