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
use Sassnowski\Venture\Models;
use Sassnowski\Venture\Testing\WorkflowTester;
use Stubs\TestAbstractWorkflow;
use Stubs\TestWorkflow;
use Stubs\WorkflowWithParameter;

uses(TestCase::class);

it('can be started', function (): void {
    Workflow::fake();

    $workflow = TestAbstractWorkflow::start();

    expect($workflow)->toBeInstanceOf(Models\Workflow::class);
    Workflow::assertStarted(TestAbstractWorkflow::class);
});

it('passes the parameters to the constructor of the workflow', function (): void {
    Workflow::fake();

    WorkflowWithParameter::start('::param::');

    Workflow::assertStarted(
        WorkflowWithParameter::class,
        fn (WorkflowWithParameter $workflow): bool => '::param::' === $workflow->something,
    );
});

it('can create a WorkflowTester for the workflow class', function (): void {
    expect(TestWorkflow::test())->toBeInstanceOf(WorkflowTester::class);
});

it('can start a workflow on a specific connection', function (): void {
    Workflow::fake();

    WorkflowWithParameter::startOnConnection('::connection::', '::param::');

    Workflow::assertStartedOnConnection(
        WorkflowWithParameter::class,
        '::connection::',
        fn (WorkflowWithParameter $workflow) => '::param::' === $workflow->something,
    );
});

it('can start a workflow synchronously', function (): void {
    Workflow::fake();

    WorkflowWithParameter::startSync('::param::');

    Workflow::assertStartedOnConnection(
        WorkflowWithParameter::class,
        'sync',
        fn (WorkflowWithParameter $workflow) => '::param::' === $workflow->something,
    );
});
