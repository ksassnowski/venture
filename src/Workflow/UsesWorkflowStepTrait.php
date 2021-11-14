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

namespace Sassnowski\Venture\Workflow;

use Illuminate\Bus\Queueable;

/**
 * This class only exists so it can be used during static analysis in place of
 * the old trait. It should not be used directly.
 *
 * @internal
 * @psalm-suppress DeprecatedTrait
 */
final class UsesWorkflowStepTrait
{
    use \Sassnowski\Venture\WorkflowStep;
    use Queueable;
}
