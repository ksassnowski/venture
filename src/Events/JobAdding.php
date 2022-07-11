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

use DateInterval;
use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;
use Sassnowski\Venture\WorkflowDefinition;

final class JobAdding
{
    use Dispatchable;

    /**
     * @param array<int, string>                      $dependencies
     * @param null|DateInterval|DateTimeInterface|int $delay
     */
    public function __construct(
        public WorkflowDefinition $definition,
        public object $job,
        public array $dependencies,
        public ?string $name,
        public mixed $delay,
        public ?string $jobID,
    ) {
    }
}
