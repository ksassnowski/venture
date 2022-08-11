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

namespace Stubs;

use Sassnowski\Venture\WorkflowableJob;
use Sassnowski\Venture\WorkflowStep;

class TestJobWithHandle implements WorkflowableJob
{
    use WorkflowStep;

    public function handle()
    {
        return true;
    }
}
