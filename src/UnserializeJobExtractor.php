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

namespace Sassnowski\Venture;

use Illuminate\Contracts\Queue\Job;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;

final class UnserializeJobExtractor implements JobExtractor
{
    public function __construct(private WorkflowJobSerializer $serializer)
    {
    }

    public function extractWorkflowJob(Job $queueJob): ?WorkflowableJob
    {
        $payload = $queueJob->payload();
        $commandName = $payload['data']['commandName'] ?? null;

        if (null === $commandName || !\class_exists($commandName)) {
            return null;
        }

        if (!\is_a($commandName, WorkflowableJob::class, true)
            && !\in_array(WorkflowStep::class, \class_uses_recursive($commandName), true)) {
            return null;
        }

        return $this->serializer->unserialize($payload['data']['command']);
    }
}
