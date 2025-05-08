<?php

namespace Hawraz\LivewireCrudGenerator;

use Illuminate\Support\ServiceProvider;
use Hawraz\LivewireCrudGenerator\Console\Commands\MakeCrudCommand;

class LivewireCrudGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Register the command
            $this->commands([
                MakeCrudCommand::class,
            ]);

            // Publish configuration file
            $this->publishes([
                __DIR__ . '/../config/livewire-crud-generator.php' => config_path('livewire-crud-generator.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/livewire-crud-generator.php', 'livewire-crud-generator'
        );
    }
}