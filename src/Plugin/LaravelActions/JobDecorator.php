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

use Lorisleiva\Actions\Decorators\JobDecorator as BaseJobDecorator;
use Sassnowski\Venture\WorkflowableJob;
use Sassnowski\Venture\WorkflowStep;

class JobDecorator extends BaseJobDecorator implements WorkflowableJob
{
    use WorkflowStep;
}
