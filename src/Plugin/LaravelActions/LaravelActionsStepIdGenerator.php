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

namespace Sassnowski\Venture\Plugin\LaravelActions;

use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\StepIdGenerator;

final class LaravelActionsStepIdGenerator implements StepIdGenerator
{
    public function __construct(
        private readonly ClassNameStepIdGenerator $idGenerator,
    ) {
    }

    public function generateId(object $job): string
    {
        if ($job instanceof \Lorisleiva\Actions\Decorators\JobDecorator) {
            $job = $job->getAction();
        }

        return $this->idGenerator->generateId($job);
    }
}
