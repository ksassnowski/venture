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

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowWithWorkflow extends AbstractWorkflow
{
    public function __construct(public $workflow)
    {
    }

    public function definition(): WorkflowDefinition
    {
        return $this->define('::name::')
            ->addWorkflow($this->workflow);
    }
}
