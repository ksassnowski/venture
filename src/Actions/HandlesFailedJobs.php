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

namespace Sassnowski\Venture\Actions;

use Sassnowski\Venture\WorkflowableJob;

interface HandlesFailedJobs
{
    public function __invoke(
        WorkflowableJob $step,
        \Throwable $exception,
    ): void;
}
