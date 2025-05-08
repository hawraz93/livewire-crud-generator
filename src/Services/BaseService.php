<?php

namespace Hawraz\LivewireCrudGenerator\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Exception;

abstract class BaseService
{
    /**
     * Parse columns from string format to array format
     * 
     * @param string $columns
     * @return array
     */
    protected function parseColumns(string $columns): array
    {
        try {
            $columnsArray = explode(',', $columns);
            $parsedColumns = [];

            foreach ($columnsArray as $column) {
                $parts = explode(':', $column);
                $name = $parts[0] ?? null;
                $type = $parts[1] ?? 'string';
                $rules = isset($parts[2]) ? explode('|', $parts[2]) : ['nullable', 'string', 'max:255'];

                if (empty($name)) {
                    throw new Exception("Column name cannot be empty in column definition: $column");
                }

                $parsedColumns[] = [
                    'name' => $name,
                    'type' => $type,
                    'rules' => $rules
                ];
            }

            return $parsedColumns;
        } catch (Exception $e) {
            Log::error("Error parsing columns: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create directory if it doesn't exist
     * 
     * @param string $directory
     * @return void
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!File::exists($directory)) {
            try {
                File::makeDirectory($directory, 0755, true);
            } catch (Exception $e) {
                Log::error("Failed to create directory: $directory", [
                    'error' => $e->getMessage()
                ]);
                throw new Exception("Failed to create directory: $directory. " . $e->getMessage());
            }
        }
    }

    /**
     * Write content to file
     * 
     * @param string $path
     * @param string $content
     * @return bool
     */
    protected function writeFile(string $path, string $content): bool
    {
        try {
            File::put($path, $content);
            return true;
        } catch (Exception $e) {
            Log::error("Failed to write file: $path", [
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to write file: $path. " . $e->getMessage());
        }
    }

    /**
     * Check if file already exists to prevent overwriting
     * 
     * @param string $path
     * @return bool
     */
    protected function fileExists(string $path): bool
    {
        return File::exists($path);
    }
}