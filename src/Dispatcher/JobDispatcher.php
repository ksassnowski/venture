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

namespace Sassnowski\Venture\Dispatcher;

use Sassnowski\Venture\WorkflowStepInterface;

interface JobDispatcher
{
    /**
     * @param array<int, WorkflowStepInterface> $jobs
     */
    public function dispatch(array $jobs): void;

    public function dispatchDependentJobs(WorkflowStepInterface $step): void;
}
