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

namespace Sassnowski\Venture\Collection;

use Sassnowski\Venture\Workflow\JobDefinition;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;

/**
 * @extends KeyedCollection<JobDefinition>
 */
final class JobDefinitionCollection extends KeyedCollection
{
    /**
     * @return WorkflowStepInterface[]
     */
    public function getInstances(): array
    {
        return \array_map(
            fn (JobDefinition $jobDefinition) => $jobDefinition->job,
            $this->items,
        );
    }
}
