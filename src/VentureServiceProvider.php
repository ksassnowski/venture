<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\Manager\WorkflowManager;
use Illuminate\Contracts\Foundation\Application;

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
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/venture.php',
            'venture'
        );

        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->app['events']->subscribe(WorkflowEventSubscriber::class);

        $this->app->bind('venture.manager', function (Application $app) {
            return new WorkflowManager(
                $app->get(Dispatcher::class),
                $app->get(config('venture.workflow_step_id_generator_class')),
            );
        });
    }
}
