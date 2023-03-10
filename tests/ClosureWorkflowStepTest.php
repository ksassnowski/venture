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

use Sassnowski\Venture\ClosureWorkflowStep;
use Sassnowski\Venture\Dispatcher\FakeDispatcher;
use Sassnowski\Venture\Dispatcher\JobDispatcher;
use Sassnowski\Venture\WorkflowableJob;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__callback.called'] = 0;
});

it('executes the wrapped closure', function (): void {
    $step = new ClosureWorkflowStep(function (): void {
        ++$_SERVER['__callback.called'];
    });

    $step->handle();

    expect($_SERVER['__callback.called'])->toBe(1);
});

it('can be serialized', function (): void {
    $step = new ClosureWorkflowStep(function (): void {
        ++$_SERVER['__callback.called'];
    });

    $step = \unserialize(\serialize($step));
    $step->handle();

    expect($_SERVER['__callback.called'])->toBe(1);
});

it('resolves the closure\'s dependencies from the container', function (): void {
    app()->instance(JobDispatcher::class, new FakeDispatcher());

    $step = new ClosureWorkflowStep(function (JobDispatcher $dispatcher): void {
        expect($dispatcher)->toBeInstanceOf(FakeDispatcher::class);
    });

    $step = \unserialize(\serialize($step));
    $step->handle();
});

it('passes the workflow step to the closure', function (): void {
    $passedStep = null;
    $step = new ClosureWorkflowStep(function (WorkflowableJob $job) use (&$passedStep): void {
        $passedStep = $job;
    });

    $step->handle();

    expect($passedStep)->toBe($step);
});
