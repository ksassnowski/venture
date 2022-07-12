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

use Sassnowski\Venture\ClosureWorkflowStep;

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
