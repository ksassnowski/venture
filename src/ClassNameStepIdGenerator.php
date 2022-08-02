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

final class ClassNameStepIdGenerator implements StepIdGeneratorInterface
{
    public function generateId(object $job): string
    {
        if ($job instanceof WorkflowStepAdapter) {
            $job = $job->unwrap();
        }

        return \get_class($job);
    }
}
