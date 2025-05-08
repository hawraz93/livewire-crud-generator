<?php

namespace Hawraz\LivewireCrudGenerator\Tests;

use Hawraz\LivewireCrudGenerator\LivewireCrudGeneratorServiceProvider;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeCrudCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            LivewireCrudGeneratorServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create necessary directories for testing
        if (!File::exists(app_path('Models'))) {
            File::makeDirectory(app_path('Models'), 0755, true);
        }
        
        if (!File::exists(app_path('Livewire/Forms'))) {
            File::makeDirectory(app_path('Livewire/Forms'), 0755, true);
        }
        
        if (!File::exists(app_path('Livewire/Test'))) {
            File::makeDirectory(app_path('Livewire/Test'), 0755, true);
        }
        
        if (!File::exists(resource_path('views/livewire/test'))) {
            File::makeDirectory(resource_path('views/livewire/test'), 0755, true);
        }
        
        // Add a routes file for testing
        File::put(base_path('routes/web.php'), '<?php

use Illuminate\Support\Facades\Route;

Route::get(\'/\', function () {
    return view(\'welcome\');
});
');
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (File::exists(app_path('Models/Product.php'))) {
            File::delete(app_path('Models/Product.php'));
        }
        
        if (File::exists(app_path('Livewire/Forms/ProductForm.php'))) {
            File::delete(app_path('Livewire/Forms/ProductForm.php'));
        }
        
        if (File::exists(app_path('Livewire/Test/ProductRegister.php'))) {
            File::delete(app_path('Livewire/Test/ProductRegister.php'));
        }
        
        if (File::exists(resource_path('views/livewire/test/product-register.blade.php'))) {
            File::delete(resource_path('views/livewire/test/product-register.blade.php'));
        }
        
        // Clean up migrations
        $migrations = File::glob(database_path('migrations/*_create_products_table.php'));
        foreach ($migrations as $migration) {
            File::delete($migration);
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_model_and_form()
    {
        $this->artisan('make:crud Test.Product --columns="name:string:required|max:255,price:decimal:required|min:0" --model --form --except=migration,component,route,modal')
            ->assertExitCode(0);
            
        $this->assertTrue(File::exists(app_path('Models/Product.php')));
        $this->assertTrue(File::exists(app_path('Livewire/Forms/ProductForm.php')));
        
        // Check content of model
        $modelContent = File::get(app_path('Models/Product.php'));
        $this->assertStringContainsString('class Product extends Model', $modelContent);
        $this->assertStringContainsString('protected $table = \'products\';', $modelContent);
        
        // Check content of form
        $formContent = File::get(app_path('Livewire/Forms/ProductForm.php'));
        $this->assertStringContainsString('class ProductForm extends Form', $formContent);
        $this->assertStringContainsString('\'name\' => \'required|max:255\'', $formContent);
        $this->assertStringContainsString('\'price\' => \'required|min:0\'', $formContent);
    }

    /** @test */
    public function it_can_create_component_and_view()
    {
        // Create a trait file required by the component
        File::makeDirectory(app_path('Traits/DataTable'), 0755, true);
        File::put(app_path('Traits/DataTable/WithPerPagePagination.php'), '<?php
        namespace App\Traits\DataTable;

        trait WithPerPagePagination
        {
            public function applyPagination($query)
            {
                return $query->paginate(10);
            }
        }');

                File::put(app_path('Traits/DataTable/WithSorting.php'), '<?php
        namespace App\Traits\DataTable;

        trait WithSorting
        {
            public function sortBy($field)
            {
                // Sort logic
            }
        }');

        File::makeDirectory(app_path('Traits'), 0755, true);
        File::put(app_path('Traits/HandlesDeletion.php'), '<?php
        namespace App\Traits;

        trait HandlesDeletion
        {
            public function deleteIfNoChildren($model)
            {
                // Delete logic
            }
        }');
        
        $this->artisan('make:crud Test.Product --columns="name:string:required|max:255,price:decimal:required|min:0" --component --except=migration,model,form,route,modal')
            ->assertExitCode(0);
            
        $this->assertTrue(File::exists(app_path('Livewire/Test/ProductRegister.php')));
        $this->assertTrue(File::exists(resource_path('views/livewire/test/product-register.blade.php')));
        
        // Check content of component
        $componentContent = File::get(app_path('Livewire/Test/ProductRegister.php'));
        $this->assertStringContainsString('class ProductRegister extends Component', $componentContent);
        $this->assertStringContainsString('use WithSorting, WithPerPagePagination, HandlesDeletion', $componentContent);
        
        // Clean up trait files
        File::deleteDirectory(app_path('Traits'));
    }
}