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

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\WorkflowDefinition;

final class TestWorkflow extends AbstractWorkflow
{
    public function definition(): WorkflowDefinition
    {
        return $this->define('::workflow-name::');
    }
}
