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

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Testing\WorkflowTester;
use Stubs\TestJob1;
use Stubs\WorkflowWithCallbacks;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__then.called'] = 0;
    $_SERVER['__catch.called'] = 0;
    $_SERVER['__workflow'] = null;
});

it('can run the then-callback of the workflow', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            then: fn () => ++$_SERVER['__then.called'],
        ),
    );

    $tester->runThenCallback();

    expect($_SERVER['__then.called'])->toBe(1);
});

it('accepts callback to configure the workflow before calling the then-callback', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            then: fn (Workflow $model) => $_SERVER['__workflow'] = $model,
        ),
    );

    $tester->runThenCallback(function (Workflow $workflow): void {
        $workflow->jobs_processed = 100;
    });

    expect($_SERVER['__workflow'])->jobs_processed->toBe(100);
});

it('can run the catch-callback of a workflow', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            catch: fn () => ++$_SERVER['__catch.called'],
        ),
    );

    $tester->runCatchCallback(new TestJob1(), new Exception());

    expect($_SERVER['__catch.called'])->toBe(1);
});

it('can configure the workflow before calling the catch-callback', function (): void {
    $tester = new WorkflowTester(
        new WorkflowWithCallbacks(
            catch: fn (Workflow $model) => $_SERVER['__workflow'] = $model,
        ),
    );

    $tester->runCatchCallback(
        new TestJob1(),
        new Exception(),
        function (Workflow $workflow): void {
            $workflow->jobs_processed = 200;
        },
    );

    expect($_SERVER['__workflow'])->jobs_processed->toBe(200);
});
