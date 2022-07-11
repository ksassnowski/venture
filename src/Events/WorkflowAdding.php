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

namespace Sassnowski\Venture\Events;

use Sassnowski\Venture\WorkflowDefinition;

final class WorkflowAdding
{
    /**
     * @param array<int, string> $dependencies
     */
    public function __construct(
        public WorkflowDefinition $parentDefinition,
        public WorkflowDefinition $nestedDefinition,
        public array $dependencies,
        public string $workflowID,
    ) {
    }
}
