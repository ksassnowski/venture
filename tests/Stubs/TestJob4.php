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

namespace Stubs;

use Illuminate\Bus\Queueable;
use Sassnowski\Venture\WorkflowStep;
use Sassnowski\Venture\WorkflowStepInterface;

class TestJob4 implements WorkflowStepInterface
{
    use Queueable;
    use WorkflowStep;
}
