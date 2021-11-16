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

namespace Sassnowski\Venture\Listener;

use Illuminate\Queue\Events\JobProcessed;
use Sassnowski\Venture\JobExtractor;
use Sassnowski\Venture\Persistence\WorkflowRepository;

final class JobProcessedListener
{
    public function __construct(
        private JobExtractor $extractor,
        private WorkflowRepository $repository,
    ) {
    }

    public function handle(JobProcessed $event): void
    {
        if (null === ($job = $this->extractor->extract($event->job))) {
            return;
        }

        $this->repository->markStepAsFinished($job);
    }
}
