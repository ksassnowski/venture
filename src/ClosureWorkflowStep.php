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

namespace Sassnowski\Venture;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;

final class ClosureWorkflowStep implements WorkflowStepInterface
{
    use WorkflowStep;

    private SerializableClosure $callback;

    public function __construct(Closure $callback)
    {
        $this->callback = new SerializableClosure($callback);
    }

    public function handle(): void
    {
        ($this->callback)();
    }
}
