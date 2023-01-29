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

interface JobExtractor
{
    /**
     * Extracts a workflow job instance from a queue job. Should return
     * null if the job instance is not a workflow job.
     */
    public function extractWorkflowJob(Job $queueJob): ?WorkflowableJob;
}
