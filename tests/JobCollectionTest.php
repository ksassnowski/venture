<?php declare(strict_types=1);

use Sassnowski\Venture\JobCollection;
use Sassnowski\Venture\JobDefinition;
use Sassnowski\Venture\Exceptions\DuplicateJobException;

it('can find job definitions by their id', function () {
    $definition1 = new JobDefinition('::id-1::', '::name-1::', new stdClass());
    $definition2 = new JobDefinition('::id-2::', '::name-2::', new stdClass());
    $collection = new JobCollection($definition1, $definition2);

    expect($collection)->find('::id-1::')->toBe($definition1);
    expect($collection)->find('::id-2::')->toBe($definition2);
});

it('returns null if no definition exists for the given id', function (JobCollection $collection) {
    expect($collection)->find('::id::')->toBeNull();
})->with([
    'empty collection' => [
        new JobCollection()
    ],
    'collection with definition with different id' => [
        new JobCollection(
            new JobDefinition('::other-id::', '::other-name::', new stdClass())
        ),
    ],
]);

it('can add new definitions', function () {
    $collection = new JobCollection();

    $definition = new JobDefinition('::id::', '::name::', new stdClass());
    $collection->add($definition);

    expect($collection)->find('::id::')->toBe($definition);
});

it('is countable', function () {
    $collection = new JobCollection();
    expect($collection)->toHaveCount(0);

    $collection->add(
        new JobDefinition('::id-1::', '::name-1::', new stdClass())
    );
    expect($collection)->toHaveCount(1);

    $collection->add(
        new JobDefinition('::id-2::', '::name-2::', new stdClass())
    );
    expect($collection)->toHaveCount(2);
});

it('is empty by default', function () {
    expect(new JobCollection())->toBeEmpty();
});

it('can be iterated over', function () {
    $definitions = [
        '::id-1::' => new JobDefinition('::id-1::', '::name-1::', new stdClass()),
        '::id-2::' => new JobDefinition('::id-2::', '::name-2::', new stdClass()),
        '::id-3::' => new JobDefinition('::id-3::', '::name-3::', new stdClass()),
    ];
    $collection = new JobCollection(...$definitions);

    foreach ($collection as $id => $jobDefinition) {
        expect($jobDefinition)->toBe($definitions[$id]);
        unset($definitions[$id]);
    }

    expect($definitions)->toBeEmpty();
});

it('throws an exception if a job definition with the same id already exists', function () {
    $definition = new JobDefinition('::id::', '::name::', new stdClass());
    $collection = new JobCollection($definition);

    $collection->add($definition);
})->throws(DuplicateJobException::class);
