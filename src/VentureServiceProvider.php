<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\Manager\WorkflowManager;
use Illuminate\Contracts\Foundation\Application;
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

        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->app['events']->subscribe(WorkflowEventSubscriber::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/venture.php',
            'venture'
        );

        $this->registerManager();
        $this->registerJobExtractor();
        $this->registerStepIdGenerator();

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
                UnserializeJobExtractor::class
            )
        );
    }

    private function registerStepIdGenerator(): void
    {
        $this->app->bind(
            StepIdGenerator::class,
            config(
                'venture.workflow_step_id_generator_class',
                ClassNameStepIdGenerator::class,
            )
        );
    }
}
