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

namespace Sassnowski\Venture\Actions;

use Sassnowski\Venture\WorkflowableJob;
use Throwable;

final class HandleFailedJobs implements HandlesFailedJobs
{
    public function __invoke(WorkflowableJob $step, Throwable $exception): void
    {
        $workflow = $step->workflow();

        $workflow?->markJobAsFailed($step, $exception);
        $workflow?->runCatchCallback($step, $exception);
    }
}
