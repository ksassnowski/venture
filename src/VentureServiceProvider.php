<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Support\ServiceProvider;

class VentureServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/venture.php' => config_path('venture.php'),
        ]);
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/venture.php',
            'venture'
        );
    }
}
