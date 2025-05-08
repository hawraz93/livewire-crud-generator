<?php

namespace Hawraz\LivewireCrudGenerator\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateMigrationService extends BaseService
{
    /**
     * Execute the creation of migration
     * 
     * @param string $name
     * @param string $columns
     * @param bool $force
     * @return string
     */
    public function execute(string $name, string $columns, bool $force = false): string
    {
        try {
            $tableName = Str::snake(Str::pluralStudly($name));
            $timestamp = date('Y_m_d_His');
            $migrationPath = database_path("migrations/{$timestamp}_create_{$tableName}_table.php");
            
            // Check if the migration already exists (by checking for similar migration names)
            $existingMigrations = File::glob(database_path("migrations/*_create_{$tableName}_table.php"));
            if (!empty($existingMigrations) && !$force) {
                throw new Exception("Migration for table '{$tableName}' already exists.");
            }
            
            $columnsString = $this->parseColumnsForMigration($columns);
            $migrationContent = $this->generateMigrationContent($tableName, $columnsString);
            
            if ($this->writeFile($migrationPath, $migrationContent)) {
                return "Migration created successfully: " . basename($migrationPath);
            }
            
            return "Failed to create migration file.";
        } catch (Exception $e) {
            Log::error("Error creating migration: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse columns for migration
     * 
     * @param string $columns
     * @return string
     */
    protected function parseColumnsForMigration(string $columns): string
    {
        $parsedColumns = $this->parseColumns($columns);
        $columnsString = '';

        foreach ($parsedColumns as $column) {
            // Map Laravel column types to migration methods
            $columnMethod = $this->mapColumnTypeToMethod($column['type']);
            $nullable = in_array('nullable', $column['rules']) ? '->nullable()' : '';
            $unique = in_array('unique', $column['rules']) ? '->unique()' : '';
            
            // Handle special cases for column types
            if ($column['type'] === 'string' && in_array('max:255', $column['rules'])) {
                $columnsString .= "            \$table->{$columnMethod}('{$column['name']}', 255){$nullable}{$unique};\n";
            } else if (in_array($column['type'], ['decimal', 'double', 'float'])) {
                // Extract precision and scale if specified in rules like precision:8,2
                $precision = 8;
                $scale = 2;
                foreach ($column['rules'] as $rule) {
                    if (strpos($rule, 'precision:') === 0) {
                        $precisionParts = explode(',', substr($rule, 10));
                        $precision = $precisionParts[0] ?? 8;
                        $scale = $precisionParts[1] ?? 2;
                    }
                }
                $columnsString .= "            \$table->{$columnMethod}('{$column['name']}', $precision, $scale){$nullable}{$unique};\n";
            } else {
                $columnsString .= "            \$table->{$columnMethod}('{$column['name']}'){$nullable}{$unique};\n";
            }
        }

        return $columnsString;
    }

    /**
     * Map column type to migration method
     * 
     * @param string $type
     * @return string
     */
    protected function mapColumnTypeToMethod(string $type): string
    {
        $typeMapping = [
            'string' => 'string',
            'text' => 'text',
            'integer' => 'integer',
            'bigInteger' => 'bigInteger',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'time' => 'time',
            'timestamp' => 'timestamp',
            'decimal' => 'decimal',
            'double' => 'double',
            'float' => 'float',
            'json' => 'json',
            'jsonb' => 'jsonb',
        ];

        return $typeMapping[$type] ?? 'string';
    }

    /**
     * Generate migration content
     * 
     * @param string $tableName
     * @param string $columnsString
     * @return string
     */
    protected function generateMigrationContent(string $tableName, string $columnsString): string
    {
        return <<<EOD
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('$tableName', function (Blueprint \$table) {
            \$table->id();
$columnsString
            \$table->foreignId('user_id')->constrained('users');
            \$table->timestamps();
            \$table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('$tableName');
    }
};
EOD;
    }
}