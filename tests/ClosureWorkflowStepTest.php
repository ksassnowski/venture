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

use Sassnowski\Venture\ClosureWorkflowStep;
use Sassnowski\Venture\Dispatcher\FakeDispatcher;
use Sassnowski\Venture\Dispatcher\JobDispatcherInterface;

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
    app()->instance(JobDispatcherInterface::class, new FakeDispatcher());

    $step = new ClosureWorkflowStep(function (JobDispatcherInterface $dispatcher): void {
        expect($dispatcher)->toBeInstanceOf(FakeDispatcher::class);
    });

    $step = \unserialize(\serialize($step));
    $step->handle();
});
