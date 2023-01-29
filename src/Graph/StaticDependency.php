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

namespace Sassnowski\Venture\Graph;

final class StaticDependency implements Dependency
{
    public function __construct(private string $dependencyID)
    {
    }

    public function getID(DependencyGraph $graph): string
    {
        return $this->dependencyID;
    }
}
