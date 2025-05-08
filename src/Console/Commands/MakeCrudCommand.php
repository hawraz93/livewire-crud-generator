<?php

namespace Hawraz\LivewireCrudGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Hawraz\LivewireCrudGenerator\Services\CreateMigrationService;
use Hawraz\LivewireCrudGenerator\Services\CreateModelService;
use Hawraz\LivewireCrudGenerator\Services\CreateLivewireFormService;
use Hawraz\LivewireCrudGenerator\Services\CreateLivewireComponentService;
use Hawraz\LivewireCrudGenerator\Services\CreateRouteService;
use Hawraz\LivewireCrudGenerator\Services\CreateLivewireModalComponentService;
use Exception;

class MakeCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud 
                            {name : The name of the model}
                            {--columns= : The columns with their types and validation rules (e.g. name:string:required|max:255,email:string:required|email)}
                            {--migration : Generate a migration}
                            {--model : Generate a model}
                            {--form : Generate a Livewire form}
                            {--component : Generate a Livewire component}
                            {--route : Generate a route}
                            {--modal : Generate a Livewire modal component}
                            {--except= : Comma separated list of items to exclude (e.g. migration,model)}
                            {--force : Force overwrite existing files}
                            {--ui=tailwind : UI framework to use (tailwind, bootstrap)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create CRUD operations with Livewire including dynamic columns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $inputName = $this->argument('name');
            $nameParts = explode('.', $inputName);

            if (count($nameParts) > 1) {
                $directoryName = $nameParts[0];
                $name = ucfirst($nameParts[1]);
            } else {
                $directoryName = '';
                $name = ucfirst($nameParts[0]);
            }

            $lowerName = strtolower($name);
            $columns = $this->option('columns');
            $uiFramework = $this->option('ui');
            $force = $this->option('force');
            $except = $this->option('except') ? explode(',', $this->option('except')) : [];

            // Check if any of the specific options are provided
            $noOptionsSpecified = !$this->option('migration') && !$this->option('model') && !$this->option('form') 
                                && !$this->option('component') && !$this->option('route') && !$this->option('modal');

            // If no specific options are provided, set all to true
            if ($noOptionsSpecified) {
                $this->input->setOption('migration', true);
                $this->input->setOption('model', true);
                $this->input->setOption('form', true);
                $this->input->setOption('component', true);
                $this->input->setOption('route', true);
                $this->input->setOption('modal', true);
            }

            if (!$columns && ($this->option('migration') || $this->option('form') || $this->option('component') || $this->option('modal'))) {
                $this->error('No columns provided. Please use --columns="col1:type1:rule1,rule2,col2:type2:rule1,rule2,..."');
                return 1;
            }

            $namespace = config('livewire.class_namespace', 'App\\Livewire') . '/' . trim($directoryName, '/');
            $namespace = str_replace('\\', '/', $namespace);

            $this->line('Creating CRUD for ' . $name);
            $this->newLine();

            // Generate database migration
            if ($this->option('migration') && !in_array('migration', $except)) {
                $this->createWithFeedback(
                    'Migration',
                    function() use ($name, $columns, $force) {
                        $createMigration = new CreateMigrationService();
                        return $createMigration->execute($name, $columns, $force);
                    }
                );
            }

            // Generate model
            if ($this->option('model') && !in_array('model', $except)) {
                $this->createWithFeedback(
                    'Model',
                    function() use ($name, $columns, $force) {
                        $createModel = new CreateModelService();
                        return $createModel->execute($name, $columns, $force);
                    }
                );
            }

            // Generate Livewire form
            if ($this->option('form') && !in_array('form', $except)) {
                $this->createWithFeedback(
                    'Livewire form',
                    function() use ($name, $columns, $force) {
                        $createLivewireForm = new CreateLivewireFormService();
                        return $createLivewireForm->execute($name, $columns, $force);
                    }
                );
            }

            // Generate Livewire component
            if ($this->option('component') && !in_array('component', $except)) {
                $this->createWithFeedback(
                    'Livewire component',
                    function() use ($name, $lowerName, $columns, $namespace, $directoryName, $uiFramework, $force) {
                        $createLivewireComponent = new CreateLivewireComponentService($uiFramework);
                        return $createLivewireComponent->execute($name, $lowerName, $columns, $namespace, $directoryName, $force);
                    }
                );
            }

            // Generate Livewire modal component
            if ($this->option('modal') && !in_array('modal', $except)) {
                $this->createWithFeedback(
                    'Livewire modal component',
                    function() use ($name, $lowerName, $columns, $namespace, $directoryName, $uiFramework, $force) {
                        $createLivewireModalComponent = new CreateLivewireModalComponentService($uiFramework);
                        return $createLivewireModalComponent->execute($name, $lowerName, $columns, $namespace, $directoryName, $force);
                    }
                );
            }

            // Generate route
            if ($this->option('route') && !in_array('route', $except)) {
                $this->createWithFeedback(
                    'Route',
                    function() use ($name, $lowerName, $namespace) {
                        $createRoute = new CreateRouteService();
                        return $createRoute->execute($name, $lowerName, $namespace);
                    }
                );
            }

            $this->newLine();
            $this->info('CRUD for ' . $name . ' created successfully!');
            return 0;
            
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create item with feedback
     * 
     * @param string $type
     * @param callable $callback
     * @return void
     */
    protected function createWithFeedback(string $type, callable $callback): void
    {
        $this->line("<fg=yellow>Creating {$type}...</>");
        
        try {
            $result = $callback();
            $this->line("<fg=green>✓</> {$result}");
        } catch (Exception $e) {
            $this->line("<fg=red>✗</> Failed to create {$type}: {$e->getMessage()}");
        }
    }
}