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

use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\JobExtractor;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Serializer\Base64WorkflowSerializer;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Sassnowski\Venture\StepIdGenerator;
use Sassnowski\Venture\UnserializeJobExtractor;
use Stubs\TestJob1;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__then_called'] = 0;
    $_SERVER['__catch_called'] = 0;
});

it('can handle missing class keys in config', function (string $abstract, string $defaultClass): void {
    config([
        'venture' => [
            'workflow_table' => 'workflows',
            'jobs_table' => 'workflow_jobs',
        ],
    ]);

    expect(app($abstract))->toBeInstanceOf($defaultClass);
})->with([
    'workflow manager' => [
        'venture.manager',
        WorkflowManager::class,
    ],
    'job extractor' => [
        JobExtractor::class,
        UnserializeJobExtractor::class,
    ],
    'workflow step id generator' => [
        StepIdGenerator::class,
        ClassNameStepIdGenerator::class,
    ],
    'workflow serializer' => [
        WorkflowJobSerializer::class,
        Base64WorkflowSerializer::class,
    ],
]);

it('can handle old workflows that still use opis/closure for their then_callback', function (): void {
    $workflow = createWorkflow([
        'then_callback' => \serialize(SerializableClosure::from(function (): void {
            ++$_SERVER['__then_called'];
        })),
    ]);

    $workflow->runThenCallback();

    expect($_SERVER['__then_called'])->toBe(1);
});

it('can handle old workflows that still use opis/closure for their catch_callback', function (): void {
    $workflow = createWorkflow([
        'catch_callback' => \serialize(SerializableClosure::from(function (): void {
            ++$_SERVER['__catch_called'];
        })),
    ]);

    $workflow->runCatchCallback(new TestJob1(), new Exception());

    expect($_SERVER['__catch_called'])->toBe(1);
});
