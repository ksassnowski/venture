<?php

namespace Sassnowski\Venture\Listener;

use Illuminate\Queue\Events\JobProcessed;
use Sassnowski\Venture\JobExtractor;
use Sassnowski\Venture\Persistence\WorkflowRepository;

final class JobProcessedListener
{
    public function __construct(
        private JobExtractor $extractor,
        private WorkflowRepository $repository,
    )
    {
    }

    public function handle(JobProcessed $event): void
    {
        if (($job = $this->extractor->extract($event->job)) === null) {
            return;
        }

        $this->repository->markStepAsFinished($job);
    }
}