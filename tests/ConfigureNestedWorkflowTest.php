<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\FakeIDGenerator;
use Sassnowski\Venture\Listeners\ConfigureNestedWorkflow;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\TestWorkflow;
use Stubs\WorkflowWithJob;

uses(TestCase::class)->group('plugin');

beforeEach(function (): void {
    $this->listener = new ConfigureNestedWorkflow(
        new FakeIDGenerator('::fake-id::'),
    );
    $this->definition = new WorkflowDefinition(new TestWorkflow());
});

it('generates an ID for a nested workflow if no explicit ID was provided', function (): void {
    $definition = createDefinition();
    $workflow = new WorkflowWithJob();
    $event = new WorkflowAdding($definition, $workflow->getDefinition(), '');

    ($this->listener)($event);

    expect($event->workflowID)->toBe('::fake-id::');
});

it('does not overwrite the workflow ID if it was provided', function (): void {
    $definition = createDefinition();
    $workflow = new WorkflowWithJob();
    $event = new WorkflowAdding($definition, $workflow->getDefinition(), '::workflow-id::');

    ($this->listener)($event);

    expect($event->workflowID)->toBe('::workflow-id::');
});

it('namespaces the nested workflow\'s job ids', function (): void {
    $definition = createDefinition();
    $nestedDefinition = (new WorkflowWithJob())->getDefinition();
    $event = new WorkflowAdding($definition, $nestedDefinition, '::workflow-id::');

    ($this->listener)($event);

    foreach ($nestedDefinition->jobs() as $jobID => $job) {
        expect($job->getJobId())->toBe('::workflow-id::.' . $jobID);
    }
});
