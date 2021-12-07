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

use Sassnowski\Venture\Facades\Workflow;
use Sassnowski\Venture\Models\Workflow as WorkflowModel;
use Stubs\TestAbstractWorkflow;
use Stubs\WorkflowWithParameter;
use function PHPUnit\Framework\assertInstanceOf;

uses(TestCase::class);

it('can be started', function (): void {
    Workflow::fake();

    $workflow = TestAbstractWorkflow::start();

    assertInstanceOf(WorkflowModel::class, $workflow);
    Workflow::assertStarted(TestAbstractWorkflow::class);
});

it('passes the parameters to the constructor of the workflow', function (): void {
    Workflow::fake();

    WorkflowWithParameter::start('::param::');

    Workflow::assertStarted(WorkflowWithParameter::class, function (WorkflowWithParameter $workflow) {
        return '::param::' === $workflow->something;
    });
});
