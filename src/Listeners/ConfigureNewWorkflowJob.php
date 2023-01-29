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

namespace Sassnowski\Venture\Listeners;

use Illuminate\Support\Str;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\StepIdGenerator;

final class ConfigureNewWorkflowJob
{
    public function __construct(private StepIdGenerator $stepIdGenerator)
    {
    }

    public function __invoke(JobAdding $event): void
    {
        $job = $event->job;

        $job->withName($event->name ?: $job::class);

        $jobID = $event->jobID ?: $this->stepIdGenerator->generateId($event->job);
        $job->withJobId($jobID);

        if (null === $job->getStepId()) {
            $job->withStepId(Str::orderedUuid());
        }

        $job->withDelay($event->delay);
    }
}
