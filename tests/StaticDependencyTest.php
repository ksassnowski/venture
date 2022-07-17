<?php

declare(strict_types=1);

use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Graph\StaticDependency;

it('returns the provided id', function (): void {
    $dependency = new StaticDependency('::job-id::');

    expect($dependency)->getID(new DependencyGraph())->toBe('::job-id::');
});