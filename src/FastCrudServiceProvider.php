<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Laravel Fast CRUD package.
 *
 * Registers the package configuration and publishes it for customization.
 */
class FastCrudServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/fast_crud.php',
            'devToolbelt.fast_crud'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/fast_crud.php' => config_path('devToolbelt/fast_crud.php'),
            ], 'fast-crud-config');
        }
    }
}
