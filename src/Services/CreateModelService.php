<?php

namespace Hawraz\LivewireCrudGenerator\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateModelService extends BaseService
{
    /**
     * Execute the creation of model
     * 
     * @param string $name
     * @param string $columns
     * @param bool $force
     * @return string
     */
    public function execute(string $name, string $columns = '', bool $force = false): string
    {
        try {
            $modelName = ucfirst($name);
            $pluralName = Str::snake(Str::pluralStudly($name));
            $modelPath = app_path("Models/{$modelName}.php");

            // Check if the model already exists
            if ($this->fileExists($modelPath) && !$force) {
                throw new Exception("Model {$modelName} already exists.");
            }

            // Ensure directory exists
            $this->ensureDirectoryExists(app_path('Models'));

            // Generate fillable fields from columns if provided
            $fillableFields = '';
            if (!empty($columns)) {
                $parsedColumns = $this->parseColumns($columns);
                $fillableArray = array_map(function ($column) {
                    return "        '{$column['name']}'";
                }, $parsedColumns);
                $fillableFields = "    protected \$fillable = [\n" . implode(",\n", $fillableArray) . "\n    ];\n\n";
            }

            $modelContent = $this->generateModelContent($modelName, $pluralName, $fillableFields);

            if ($this->writeFile($modelPath, $modelContent)) {
                return "Model created successfully: {$modelName}";
            }

            return "Failed to create model file.";
        } catch (Exception $e) {
            Log::error("Error creating model: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate model content
     * 
     * @param string $modelName
     * @param string $pluralName
     * @param string $fillableFields
     * @return string
     */
    protected function generateModelContent(string $modelName, string $pluralName, string $fillableFields): string
    {
        // Check if the CreatedById trait exists
        $usesCreatedById = class_exists('App\Traits\CreatedById') ? ", CreatedById" : "";
        $useCreatedByIdImport = class_exists('App\Traits\CreatedById') ? "use App\Traits\CreatedById;\n" : "";

        return <<<EOD
    <?php

    namespace App\Models;

    {$useCreatedByIdImport}use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\SoftDeletes;

    class {$modelName} extends Model
    {
        use HasFactory, SoftDeletes{$usesCreatedById};

        protected \$table = '{$pluralName}';
        
    {$fillableFields}    protected \$guarded = ['id'];
        
        /**
         * The attributes that should be cast.
         *
         * @var array
         */
        protected \$casts = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
    EOD;
    }
}