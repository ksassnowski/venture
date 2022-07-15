<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use Sassnowski\Venture\Actions\HandleFailedJobs;
use Sassnowski\Venture\State\WorkflowStateStore;
use Stubs\TestJob1;

uses(TestCase::class);

beforeEach(function (): void {
    WorkflowStateStore::fake();

    $this->action = new HandleFailedJobs();
    $_SERVER['__catch.count'] = 0;
});

it('marks the step as failed', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->build();
    $exception = new Exception();

    ($this->action)($job, $exception);

    expect(WorkflowStateStore::forWorkflow($workflow))
        ->failedJobs->toHaveKey(TestJob1::class);
    expect(WorkflowStateStore::forWorkflow($workflow)->failedJobs[TestJob1::class])
        ->toBe($exception);
});

it('runs the catch callback', function (): void {
    createDefinition()
        ->addJob($job = new TestJob1())
        ->catch(function (): void {
            ++$_SERVER['__catch.count'];
        })
        ->build();

    ($this->action)($job, new Exception());

    expect($_SERVER['__catch.count'])->toBe(1);
});
