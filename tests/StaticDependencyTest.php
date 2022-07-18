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

use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Graph\StaticDependency;

it('returns the provided id', function (): void {
    $dependency = new StaticDependency('::job-id::');

    expect($dependency)->getID(new DependencyGraph())->toBe('::job-id::');
});
