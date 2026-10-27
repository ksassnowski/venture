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

namespace Stubs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Sassnowski\Venture\WorkflowableJob;
use Sassnowski\Venture\WorkflowStep;

class RetryableJob implements ShouldQueue, WorkflowableJob
{
    use WorkflowStep;

    public function __construct(public bool $shouldFail = false)
    {
    }

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('The retry failed.');
        }
    }
}
