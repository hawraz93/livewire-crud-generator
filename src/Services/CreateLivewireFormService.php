<?php

namespace Hawraz\LivewireCrudGenerator\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateLivewireFormService extends BaseService
{
    /**
     * Execute the creation of Livewire form
     * 
     * @param string $name
     * @param string $columns
     * @param bool $force
     * @return string
     */
    public function execute(string $name, string $columns, bool $force = false): string
    {
        try {
            $formName = ucfirst($name) . 'Form';
            $formDirectory = app_path('Livewire/Forms');
            $formPath = "{$formDirectory}/{$formName}.php";
            
            // Check if the form already exists
            if ($this->fileExists($formPath) && !$force) {
                throw new Exception("Livewire form {$formName} already exists.");
            }
            
            // Ensure directory exists
            $this->ensureDirectoryExists($formDirectory);
            
            $columnsArray = $this->parseColumns($columns);
            $formContent = $this->generateFormContent($name, $columnsArray);
            
            if ($this->writeFile($formPath, $formContent)) {
                return "Livewire form created successfully: {$formName}";
            }
            
            return "Failed to create Livewire form.";
        } catch (Exception $e) {
            Log::error("Error creating Livewire form: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate form content
     * 
     * @param string $name
     * @param array $columnsArray
     * @return string
     */
    protected function generateFormContent(string $name, array $columnsArray): string
    {
        $className = ucfirst($name);
        $snakeName = Str::snake($name);
        
        // Generate properties
        $properties = array_map(function($column) {
            return "public \${$column['name']}";
        }, $columnsArray);
        $propertiesString = implode(";\n    ", $properties);
        
        // Generate rules
        $rules = array_map(function($column) {
            return "        '{$column['name']}' => '" . implode('|', $column['rules']) . "',";
        }, $columnsArray);
        $rulesString = implode("\n", $rules);
        
        return <<<EOD
<?php

namespace App\\Livewire\\Forms;

use Livewire\\Form;
use App\\Models\\{$className};

class {$className}Form extends Form
{
    public \$updateId = null;
    public \$this_{$snakeName};
    public {$propertiesString};

    /**
     * Define validation rules
     * 
     * @return array
     */
    protected function rules(): array
    {
        return [
{$rulesString}
        ];
    }

    /**
     * Save or update the record
     * 
     * @return void
     */
    public function save()
    {
        \$validatedData = \$this->validate();

        \$this->this_{$snakeName} = {$className}::updateOrCreate(
            ['id' => \$this->updateId], 
            \$validatedData
        );

        \$this->resetInputFields();
        
        return \$this->this_{$snakeName};
    }

    /**
     * Reset form input fields
     * 
     * @return void
     */
    public function resetInputFields()
    {
        \$updateId = \$this->updateId;
        \${$snakeName} = \$this->this_{$snakeName};
        \$this->reset();
        \$this->updateId = \$updateId;
        \$this->this_{$snakeName} = \${$snakeName};
        \$this->resetErrorBag();
        \$this->resetValidation();
    }

    /**
     * Set fields for editing
     * 
     * @param int \$id
     * @return void
     */
    public function setFields(\$id)
    {
        \$this->resetInputFields();
        
        \$this->this_{$snakeName} = {$className}::findOrFail(\$id);
        \$this->updateId = \$id;
        \$this->fill(\$this->this_{$snakeName}->toArray());
    }
}
EOD;
    }
}