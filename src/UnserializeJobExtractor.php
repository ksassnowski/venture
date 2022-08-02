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

namespace Sassnowski\Venture;

use Illuminate\Contracts\Queue\Job;
use Sassnowski\Venture\Serializer\WorkflowJobSerializerInterface;

final class UnserializeJobExtractor implements JobExtractorInterface
{
    public function __construct(private WorkflowJobSerializerInterface $serializer)
    {
    }

    public function extractWorkflowJob(Job $queueJob): ?WorkflowStepInterface
    {
        return $this->serializer->unserialize(
            $queueJob->payload()['data']['command'],
        );
    }
}
