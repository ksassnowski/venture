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

namespace Sassnowski\Venture\Events;

use Sassnowski\Venture\WorkflowDefinition;
use Sassnowski\Venture\WorkflowStepInterface;

final class JobAdded
{
    public function __construct(
        public WorkflowDefinition $definition,
        public WorkflowStepInterface $job,
    ) {
    }
}
