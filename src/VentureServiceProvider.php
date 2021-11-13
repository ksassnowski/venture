<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Support\ServiceProvider;
use Sassnowski\Venture\Manager\WorkflowManager;

class VentureServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/venture.php' => config_path('venture.php'),
        ], ['config', 'venture-config']);

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], ['migrations', 'venture-migrations']);

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/venture.php',
            'venture'
        );
        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->app['events']->subscribe(WorkflowEventSubscriber::class);
        $this->app->bind('venture.manager', WorkflowManager::class);
    }
}
