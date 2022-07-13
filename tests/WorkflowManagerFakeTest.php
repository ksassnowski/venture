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
use Stubs\TestWorkflow;
use Stubs\WorkflowWithParameter;

uses(TestCase::class);

beforeEach(function (): void {
    $this->managerFake = Workflow::fake();
});

it('implements the correct interface', function (): void {
    expect($this->managerFake)->toBeInstanceOf(WorkflowManagerInterface::class);
});

it('records all started workflows', function (): void {
    expect($this->managerFake)
        ->hasStarted(TestAbstractWorkflow::class)
        ->toBeFalse();
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    expect($this->managerFake)
        ->hasStarted(TestAbstractWorkflow::class)
        ->toBeTrue();
});

it('stores the workflow to the database', function (): void {
    $workflow = $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    expect($workflow)
        ->exists->toBeTrue()
        ->wasRecentlyCreated->toBeTrue();
});

test('assertStarted passes if a workflow was started', function (): void {
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    $this->managerFake->assertStarted(TestAbstractWorkflow::class);
});

test('assertStarted fails if the expected workflow was not started', function (): void {
    $this->managerFake->assertStarted(TestAbstractWorkflow::class);
})->throws(
    AssertionFailedError::class,
    'The expected workflow [Stubs\TestAbstractWorkflow] was not started',
);

test('assertStartedOnConnection passes if the workflow was started on the correct connection', function (): void {
    $this->managerFake->startWorkflow(new TestWorkflow(), '::connection::');

    $this->managerFake->assertStartedOnConnection(
        TestWorkflow::class,
        '::connection::',
    );
});

test('assertStartedOnConnection fails if the workflow was not started on the correct connection', function (): void {
    $this->managerFake->startWorkflow(new TestWorkflow(), '::connection::');

    $this->managerFake->assertStartedOnConnection(
        TestWorkflow::class,
        '::different-connection::',
    );
})->throws(
    AssertionFailedError::class,
    'The workflow [Stubs\TestWorkflow] was started, but on unexpected connection [::connection::]',
);

test('assertStartedOnConnection fails if the workflow was not started at all', function (): void {
    $this->managerFake->assertStartedOnConnection(
        TestWorkflow::class,
        '::different-connection::',
    );
})->throws(
    AssertionFailedError::class,
    'The expected workflow [Stubs\TestWorkflow] was not started',
);

test('assertStartedOnConnection fails if the workflow was started on the correct connection but the callback returns false', function (): void {
    $this->managerFake->startWorkflow(new TestWorkflow(), '::connection::');

    $this->managerFake->assertStartedOnConnection(
        TestWorkflow::class,
        '::connection::',
        fn () => false,
    );
})->throws(
    AssertionFailedError::class,
    'The expected workflow [Stubs\TestWorkflow] was not started',
);

test('assertStartedOnConnection passes if the workflow was started on the correct connection and the callback returns true', function (): void {
    $this->managerFake->startWorkflow(new TestWorkflow(), '::connection::');

    $this->managerFake->assertStartedOnConnection(
        TestWorkflow::class,
        '::connection::',
        fn (TestWorkflow $workflow, ?string $connection) => '::connection::' === $connection,
    );
});

test('assertStarted fails if the provided callback returns false', function (): void {
    $this->managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    $this->managerFake->assertStarted(
        WorkflowWithParameter::class,
        fn (WorkflowWithParameter $workflow) => '::other-input::' === $workflow->something,
    );
})->throws(
    AssertionFailedError::class,
    'The expected workflow [Stubs\WorkflowWithParameter] was not started',
);

test('assertStarted passes if the workflow was started and the callback returns true', function (): void {
    $this->managerFake->startWorkflow(new WorkflowWithParameter('::input::'));

    $this->managerFake->assertStarted(
        WorkflowWithParameter::class,
        fn (WorkflowWithParameter $workflow) => '::input::' === $workflow->something,
    );
});

test('assertNotStarted passes if the workflow was not started', function (): void {
    $this->managerFake->assertNotStarted(TestAbstractWorkflow::class);
});

test('assertNotStarted fails if the workflow was started', function (): void {
    $this->managerFake->startWorkflow(new TestAbstractWorkflow());

    $this->managerFake->assertNotStarted(TestAbstractWorkflow::class);
})->throws(
    AssertionFailedError::class,
    'The unexpected [Stubs\TestAbstractWorkflow] workflow was started',
);

test('assertNotStarted passes if a workflow was started, but the callback returns false', function (): void {
    $this->managerFake->startWorkflow(new WorkflowWithParameter('::some-parameter::'));

    $this->managerFake->assertNotStarted(
        WorkflowWithParameter::class,
        fn (WorkflowWithParameter $workflow) => '::other-parameter::' === $workflow->something,
    );
});

it('runs the beforeCreate hook', function (): void {
    $workflow = new class() extends AbstractWorkflow {
        public function definition(): WorkflowDefinition
        {
            return createDefinition('::name::');
        }

        public function beforeCreate(Sassnowski\Venture\Models\Workflow $workflow): void
        {
            $workflow->name = '::new-name::';
        }
    };

    $workflow = $this->managerFake->startWorkflow($workflow);

    expect($workflow)->name->toBe('::new-name::');
});
