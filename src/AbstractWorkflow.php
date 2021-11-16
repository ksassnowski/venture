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

namespace Sassnowski\Venture;

use Sassnowski\Venture\Workflow\WorkflowBuilder;

/**
 * @deprecated This class has been deprecated and will be removed in future versions
 *             of Venture. Workflows should extend \Sassnowski\Venture\Workflow\WorkflowBuilder
 *             instead.
 * @see \Sassnowski\Venture\Workflow\WorkflowBuilder
 */
abstract class AbstractWorkflow extends WorkflowBuilder
{
}
