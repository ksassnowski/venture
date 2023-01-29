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

use Sassnowski\Venture\Graph\ConditionalDependency;
use Sassnowski\Venture\Graph\DependencyGraph;
use Stubs\TestJob1;

it('returns the dependency it if the callback returns true', function (): void {
    $dependency = new ConditionalDependency(
        fn () => true,
        '::dependency::',
    );

    expect($dependency)->getID(new DependencyGraph())->toBe('::dependency::');
});

it('returns null it if the callback returns false and no fallback was provided', function (): void {
    $dependency = new ConditionalDependency(
        fn () => false,
        '::dependency::',
    );

    expect($dependency)->getID(new DependencyGraph())->toBeNull();
});

it('returns the fallback it if the callback returns false and a fallback was provided', function (): void {
    $dependency = new ConditionalDependency(
        fn () => false,
        '::dependency::',
        '::fallback::',
    );

    expect($dependency)->getID(new DependencyGraph())->toBe('::fallback::');
});

it('returns the dependency if the graph contains the dependency', function (): void {
    $graph = new DependencyGraph();
    $graph->addDependantJob(new TestJob1(), [], TestJob1::class);
    $dependency = ConditionalDependency::whenDefined(TestJob1::class);

    expect($dependency)->getID($graph)->toBe(TestJob1::class);
});

it('returns the fallback if the graph does not contain the dependency but a fallback was provided', function (): void {
    $graph = new DependencyGraph();
    $dependency = ConditionalDependency::whenDefined(TestJob1::class, '::fallback::');

    expect($dependency)->getID($graph)->toBe('::fallback::');
});

it('returns null if the graph does not contain the dependency and no fallback was provided', function (): void {
    $graph = new DependencyGraph();
    $dependency = ConditionalDependency::whenDefined(TestJob1::class);

    expect($dependency)->getID($graph)->toBeNull();
});
