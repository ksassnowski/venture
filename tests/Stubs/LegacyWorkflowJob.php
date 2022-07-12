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

namespace Stubs;

use Sassnowski\Venture\WorkflowStep;

final class LegacyWorkflowJob
{
    use WorkflowStep;

    public string $foo = 'bar';

    public function baz(): string
    {
        return 'qux';
    }
}
