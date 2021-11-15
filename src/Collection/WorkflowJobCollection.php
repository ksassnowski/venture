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

use Sassnowski\Venture\DTO\WorkflowJob;

/**
 * @extends KeyedCollection<WorkflowJob>
 */
final class WorkflowJobCollection extends KeyedCollection
{
    /**
     * @return WorkflowJob[]
     */
    public function failedJobs(): array
    {
        return \array_filter(
            $this->items,
            fn (WorkflowJob $job) => null !== $job->failedAt,
        );
    }
}
