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

use Sassnowski\Venture\Events\JobAdded;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\Events\JobCreated;
use Sassnowski\Venture\Events\JobCreating;
use Sassnowski\Venture\Events\JobFailed;
use Sassnowski\Venture\Events\JobFinished;
use Sassnowski\Venture\Events\JobProcessing;
use Sassnowski\Venture\Events\WorkflowAdded;
use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\Events\WorkflowCreated;
use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Events\WorkflowStarted;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Plugin\PluginContext;
use Stubs\TestJob1;
use Stubs\TestWorkflow;

uses(TestCase::class)->group('plugin');

beforeEach(function (): void {
    $_SERVER['__plugin.listener_called'] = 0;
});

it('registers an event listener', function (string $method, object $event): void {
    $context = new PluginContext(app('events'));

    $context->{$method}(function (): void {
        ++$_SERVER['__plugin.listener_called'];
    });

    \event($event);

    expect($_SERVER['__plugin.listener_called'])->toBe(1);
})->with([
    'JobAdding' => [
        'onJobAdding',
        new JobAdding((new TestWorkflow())->definition(), new TestJob1(), [], null, null, null),
    ],
    'JobAdded' => [
        'onJobAdded',
        new JobAdded((new TestWorkflow())->definition(), new TestJob1(), [], '::name::'),
    ],
    'WorkflowAdding' => [
        'onWorkflowAdding',
        new WorkflowAdding(
            (new TestWorkflow())->definition(),
            (new TestWorkflow())->definition(),
            [],
            '::workflow-id::',
        ),
    ],
    'WorkflowAdded' => [
        'onWorkflowAdded',
        new WorkflowAdded(
            (new TestWorkflow())->definition(),
            (new TestWorkflow())->definition(),
            [],
            '::workflow-id::',
        ),
    ],
    'WorkflowCreating' => [
        'onWorkflowCreating',
        fn () => new WorkflowCreating(
            (new TestWorkflow())->definition(),
            new Workflow(),
        ),
    ],
    'WorkflowCreated' => [
        'onWorkflowCreated',
        fn () => new WorkflowCreated(
            (new TestWorkflow())->definition(),
            new Workflow(),
        ),
    ],
    'JobCreating' => [
        'onJobCreating',
        fn () => new JobCreating(
            new Workflow(),
            new WorkflowJob(),
        ),
    ],
    'JobCreated' => [
        'onJobCreated',
        fn () => new JobCreated(new WorkflowJob()),
    ],
    'JobProcessing' => [
        'onJobProcessing',
        new JobProcessing(new TestJob1()),
    ],
    'JobFinished' => [
        'onJobFinished',
        new JobFinished(new TestJob1()),
    ],
    'JobFailed' => [
        'onJobFailed',
        new JobFailed(new TestJob1(), new Exception('::boom::')),
    ],
    'WorkflowStarted' => [
        'onWorkflowStarted',
        fn () => new WorkflowStarted(
            new TestWorkflow(),
            new Workflow(),
            [],
        ),
    ],
]);
