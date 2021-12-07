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

use Illuminate\Contracts\Queue\Job;
use function class_uses_recursive;

final class UnserializeJobExtractor implements JobExtractor
{
    public function extractWorkflowJob(Job $queueJob): ?object
    {
        $instance = \unserialize($queueJob->payload()['data']['command']);

        $uses = class_uses_recursive($instance);

        if (!\in_array(WorkflowStep::class, $uses, true)) {
            return null;
        }

        return $instance;
    }
}
