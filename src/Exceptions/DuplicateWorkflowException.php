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

namespace Sassnowski\Venture\Exceptions;

use Spatie\ErrorSolutions\Contracts\BaseSolution;
use Spatie\ErrorSolutions\Contracts\ProvidesSolution;
use Spatie\ErrorSolutions\Contracts\Solution;

class DuplicateWorkflowException extends \Exception implements ProvidesSolution
{
    public function getSolution(): Solution
    {
        return BaseSolution::create('Duplicate jobs')
            ->setSolutionDescription(
                'If you tried to add multiple instances of the same workflow, make sure to provide unique ids for each of them.',
            );
    }
}
