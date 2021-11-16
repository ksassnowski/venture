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

namespace Sassnowski\Venture\Persistence\Database;

use Sassnowski\Venture\DTO\Workflow;
use stdClass;

final class WorkflowFactory
{
    public function hydrateWorkflow(stdClass $row): Workflow
    {
        return new Workflow(
            (string) $row->id,
            (int) $row->job_count,
            (int) $row->jobs_failed,
            (int) $row->jobs_processed,
            \explode(',', $row->finished_jobs),
            [],
        );
    }
}
