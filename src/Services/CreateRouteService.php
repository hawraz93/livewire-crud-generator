<?php

namespace Hawraz\LivewireCrudGenerator\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateRouteService extends BaseService
{
    /**
     * Execute the creation of route
     * 
     * @param string $name
     * @param string $lowerName
     * @param string $directory
     * @return string
     */
    public function execute(string $name, string $lowerName, string $directory): string
    {
        try {
            $routePath = base_path('routes/web.php');
            $routeContent = File::get($routePath);
            
            // Ensure backslashes are escaped properly for the namespace and avoid double backslashes
            $routeNamespace = str_replace('/', '\\', trim($directory, '\\/'));
            
            // Make sure the namespace starts with "App\Livewire" and has no double backslashes
            if (!str_starts_with($routeNamespace, 'App\\Livewire')) {
                $routeNamespace = 'App\\Livewire\\' . $routeNamespace;
            }
            
            // Check if the route already exists
            $routePattern = "Route::get\('\\/{$lowerName}', {$routeNamespace}\\\\{$name}Register::class\)->";
            if (preg_match("/{$routePattern}/", $routeContent)) {
                return "Route for {$name}Register already exists.";
            }
            
            // Generate the route content with corrected single backslash
            $newRouteContent = <<<EOD

            // Route for {$name}Register
            Route::get('/{$lowerName}', {$routeNamespace}\\{$name}Register::class)->name('{$lowerName}.index');
            EOD;
            
            // Append the route to the web.php file
            File::append($routePath, $newRouteContent);
            
            return "Route for {$name}Register added successfully.";
        } catch (Exception $e) {
            Log::error("Error creating route: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if the route exists for the given name
     * 
     * @param string $name
     * @param string $lowerName
     * @param string $directory
     * @return bool
     */
    public function routeExists(string $name, string $lowerName, string $directory): bool
    {
        try {
            $routePath = base_path('routes/web.php');
            if (!File::exists($routePath)) {
                return false;
            }
            
            $routeContent = File::get($routePath);
            
            // Ensure backslashes are escaped properly for the namespace and avoid double backslashes
            $routeNamespace = str_replace('/', '\\', trim($directory, '\\/'));
            
            // Make sure the namespace starts with "App\Livewire" and has no double backslashes
            if (!str_starts_with($routeNamespace, 'App\\Livewire')) {
                $routeNamespace = 'App\\Livewire\\' . $routeNamespace;
            }
            
            $routePattern = "Route::get\('\\/{$lowerName}', {$routeNamespace}\\\\{$name}Register::class\)->";
            return preg_match("/{$routePattern}/", $routeContent) === 1;
            
        } catch (Exception $e) {
            Log::error("Error checking if route exists: " . $e->getMessage());
            return false;
        }
    }
}