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

use Sassnowski\Venture\Graph\Node;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;

it('returns its id if it has not been namespaced', function (): void {
    $node = new Node('::id::', new TestJob1(), []);

    expect($node)->getID()->toBe('::id::');
});

it('returns a namespaced it if it has been namespaced', function (): void {
    $node = new Node('::id::', new TestJob1(), []);

    $node->namespace('::namespace::');

    expect($node)->getID()->toBe('::namespace::.::id::');
});

it('returns the ids of all its dependencies', function (): void {
    $node = new Node(
        '::id::',
        new TestJob1(),
        [
            new Node('::dependency-1::', new TestJob2(), []),
            new Node('::dependency-2::', new TestJob3(), []),
        ],
    );

    expect($node)->getDependencyIDs()->toEqual([
        '::dependency-1::',
        '::dependency-2::',
    ]);
});

it('returns all its dependent jobs', function (): void {
    $node = new Node(
        '::id::',
        new TestJob1(),
        [],
        [
            new Node('::dependency-1::', $job2 = new TestJob2(), []),
            new Node('::dependency-2::', $job3 = new TestJob3(), []),
        ],
    );

    expect($node)->getDependentJobs()->toEqual([$job2, $job3]);
});

it('can add dependent nodes', function (): void {
    $node = new Node('::id::', new TestJob1(), []);

    expect($node)->getDependentJobs()->toBeEmpty();

    $node->addDependent(
        new Node('::dependency-1::', $job = new TestJob2(), []),
    );
    expect($node)->getDependentJobs()->toEqual([$job]);
});

it('returns the job instance', function (): void {
    $node = new Node('::id::', $job = new TestJob1(), []);

    expect($node)->getJob()->toBe($job);
});

it('is a root if it has not been namespaced and it has no dependencies', function (): void {
    $node = new Node('::id::', new TestJob1(), []);

    expect($node)->isRoot()->toBeTrue();
});

it('is not a root if it has not been namespaced and it has dependencies', function (): void {
    $dependency = new Node('::id-2::', new TestJob2(), []);
    $node = new Node('::id-1::', new TestJob1(), [$dependency]);

    expect($node)->isRoot()->toBeFalse();
});

it('is a root if it has been namespaced and it has no dependencies in the same namespace', function (): void {
    $dependency1 = new Node('::id-2::', new TestJob2(), []);
    $dependency2 = new Node('::id-3::', new TestJob3(), []);
    $dependency2->namespace('::namespace-2::');

    $node = new Node('::id-1::', new TestJob1(), [$dependency1, $dependency2]);
    $node->namespace('::namespace-1::');

    expect($node)->isRoot()->toBeTrue();
});

it('is not a root if it has been namespaced and it has dependencies in the same namespace', function (): void {
    $dependency = new Node('::id-2::', new TestJob2(), []);
    $dependency->namespace('::namespace-1::');

    $node = new Node('::id-1::', new TestJob1(), [$dependency]);
    $node->namespace('::namespace-1::');

    expect($node)->isRoot()->toBeFalse();
});

it('is a leaf if it has not been namespaced and it has no dependents', function (): void {
    $node = new Node('::id::', new TestJob1(), []);

    expect($node)->isLeaf()->toBeTrue();
});

it('is not a leaf if it has not been namespaced and it has dependents', function (): void {
    $node = new Node('::id::', new TestJob1(), []);

    $node->addDependent(
        new Node('::dependency::', new TestJob2(), []),
    );

    expect($node)->isLeaf()->toBeFalse();
});

it('is a leaf if it has been namespaced and it has no dependents in the same namespace', function (): void {
    $dependency1 = new Node('::id-2::', new TestJob2(), []);
    $dependency2 = new Node('::id-3::', new TestJob3(), []);
    $dependency2->namespace('::namespace-2::');

    $node = new Node('::id-1::', new TestJob1(), []);
    $node->namespace('::namespace-1::');

    $node->addDependent($dependency1);
    $node->addDependent($dependency2);

    expect($node)->isLeaf()->toBeTrue();
});

it('is not a leaf if it has been namespaced and it has dependents in the same namespace', function (): void {
    $dependency = new Node('::id-2::', new TestJob2(), []);
    $dependency->namespace('::namespace-1::');

    $node = new Node('::id-1::', new TestJob1(), []);
    $node->namespace('::namespace-1::');

    $node->addDependent($dependency);

    expect($node)->isLeaf()->toBeFalse();
});
