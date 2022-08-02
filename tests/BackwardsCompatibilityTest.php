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

use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\JobExtractorInterface;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Serializer\Base64WorkflowSerializer;
use Sassnowski\Venture\Serializer\WorkflowJobSerializerInterface;
use Sassnowski\Venture\StepIdGeneratorInterface;
use Sassnowski\Venture\UnserializeJobExtractor;
use Stubs\LegacyWorkflowJob;

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
        JobExtractorInterface::class,
        UnserializeJobExtractor::class,
    ],
    'workflow step id generator' => [
        StepIdGeneratorInterface::class,
        ClassNameStepIdGenerator::class,
    ],
    'workflow serializer' => [
        WorkflowJobSerializerInterface::class,
        Base64WorkflowSerializer::class,
    ],
]);

it('allows adding jobs to a workflow that do not implement WorkflowStepInterface as long as they use the WorkflowStep trait', function (): void {
    $definition = createDefinition()
        ->addJob(new LegacyWorkflowJob())
        ->addJob(new LegacyWorkflowJob(), id: '::legacy-job-id::');

    expect($definition)
        ->hasJob(LegacyWorkflowJob::class)->toBeTrue()
        ->hasJob('::legacy-job-id::')->toBeTrue();
});
