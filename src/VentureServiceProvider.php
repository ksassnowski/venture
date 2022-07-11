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

namespace Sassnowski\Venture;

use Illuminate\Support\ServiceProvider;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Serializer\Base64WorkflowSerializer;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use function config;

class VentureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/venture.php' => config_path('venture.php'),
        ], ['config', 'venture-config']);

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], ['migrations', 'venture-migrations']);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        /** @phpstan-ignore-next-line */
        $this->app['events']->subscribe(WorkflowEventSubscriber::class);

        Venture::bootPlugins();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/venture.php',
            'venture',
        );

        $this->registerManager();
        $this->registerJobExtractor();
        $this->registerStepIdGenerator();
        $this->registerJobSerializer();
    }

    private function registerManager(): void
    {
        $this->app->bind('venture.manager', WorkflowManager::class);
    }

    private function registerJobExtractor(): void
    {
        $this->app->bind(
            JobExtractor::class,
            config(
                'venture.workflow_job_extractor_class',
                UnserializeJobExtractor::class,
            ),
        );
    }

    private function registerStepIdGenerator(): void
    {
        $this->app->bind(
            StepIdGenerator::class,
            config(
                'venture.workflow_step_id_generator_class',
                ClassNameStepIdGenerator::class,
            ),
        );
    }

    private function registerJobSerializer(): void
    {
        $this->app->bind(
            WorkflowJobSerializer::class,
            config(
                'venture.workflow_job_serializer_class',
                Base64WorkflowSerializer::class,
            ),
        );
    }
}
