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

use Exception;
use Throwable;

final class InvalidJobException extends Exception
{
    public static function closureWithoutID(): self
    {
        return new self('Closure-based jobs must have an explicit id');
    }

    public static function jobNotUsingTrait(object $job, ?Throwable $previous = null): self
    {
        return new self(
            \sprintf(
                'Job [%s] does not implement WorkflowStepInterface or use the WorkflowStep trait',
                $job::class,
            ),
            previous: $previous,
        );
    }
}
