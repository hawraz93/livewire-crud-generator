<?php

namespace Hawraz\LivewireCrudGenerator\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateLivewireComponentService extends BaseService
{
    /**
     * @var string UI framework to use (tailwind, bootstrap)
     */
    protected $uiFramework;

    /**
     * Constructor
     * 
     * @param string $uiFramework
     */
    public function __construct(string $uiFramework = 'tailwind')
    {
        $this->uiFramework = $uiFramework;
    }

    /**
     * Execute the creation of Livewire component
     * 
     * @param string $name
     * @param string $lowerName
     * @param string $columns
     * @param string $directory
     * @param string $directoryName
     * @param bool $force
     * @return string
     */
    public function execute(
        string $name, 
        string $lowerName, 
        string $columns, 
        string $directory, 
        string $directoryName,
        bool $force = false
    ): string {
        try {
            $componentNamespace = trim(str_replace('/', '\\', $directory), '\\');
            $componentDirectory = app_path(str_replace('App/', '', $directory));
            $viewDirectory = resource_path('views/livewire/' . str_replace(['App\\Livewire\\', '\\'], ['', '/'], $componentNamespace));

            // Create component class name
            $componentClass = ucfirst($name) . 'Register';
            $componentPath = $componentDirectory . '/' . $componentClass . '.php';
            $viewPath = $viewDirectory . '/' . $lowerName . '-register.blade.php';

            // Check if files already exist
            if (!$force) {
                if ($this->fileExists($componentPath)) {
                    throw new Exception("Livewire component {$componentClass} already exists.");
                }
                if ($this->fileExists($viewPath)) {
                    throw new Exception("Livewire view {$lowerName}-register.blade.php already exists.");
                }
            }

            // Ensure directories exist
            $this->ensureDirectoryExists($componentDirectory);
            $this->ensureDirectoryExists($viewDirectory);

            $snakeName = Str::snake($name);
            $parsedColumns = $this->parseColumns($columns);

            // Generate component and view content
            $componentContent = $this->generateComponentContent($name, $componentNamespace, $directoryName, $lowerName, $snakeName);
            $viewContent = $this->generateViewContent($parsedColumns, $lowerName, $name);

            $results = [];
            
            if ($this->writeFile($componentPath, $componentContent)) {
                $results[] = "Livewire component created successfully: {$componentClass}";
            }
            
            if ($this->writeFile($viewPath, $viewContent)) {
                $results[] = "Livewire view created successfully: {$lowerName}-register.blade.php";
            }

            return implode("\n", $results);
        } catch (Exception $e) {
            Log::error("Error creating Livewire component: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate component content
     * 
     * @param string $name
     * @param string $componentNamespace
     * @param string $directoryName
     * @param string $lowerName
     * @param string $snakeName
     * @return string
     */
    protected function generateComponentContent(
        string $name, 
        string $componentNamespace, 
        string $directoryName, 
        string $lowerName,
        string $snakeName
    ): string {
        return <<<EOD
<?php

namespace $componentNamespace;

use Livewire\Component;
use App\Models\\{$name};
use App\\Livewire\\Forms\\{$name}Form;
use App\Traits\DataTable\WithPerPagePagination;
use App\Traits\DataTable\WithSorting;
use App\Traits\HandlesDeletion;
use Livewire\WithPagination;

class {$name}Register extends Component
{
    use WithSorting, WithPerPagePagination, HandlesDeletion, WithPagination;

    public \$filters = [
        'search' => '',
        'maxDate' => null,
        'minDate' => null,
    ];

    public \$showFilters = false;
    public {$name}Form \$form;
    public \$deleteModal = false;

    /**
     * Reset page when filters are updated
     */
    public function updatedFilters() 
    { 
        \$this->resetPage(); 
    }

    /**
     * Reset all filters
     */
    public function resetFilters() 
    { 
        \$this->reset('filters'); 
    }

    /**
     * Toggle filters visibility
     */
    public function toggleShowFilters() 
    { 
        \$this->showFilters = !\$this->showFilters; 
    }

    /**
     * Define query for rows
     */
    public function getRowsQueryProperty()
    {
        return {$name}::query()
            ->when(\$this->filters['maxDate'], fn (\$query, \$maxDate) => \$query->where('created_at', '<=', \$maxDate))
            ->when(\$this->filters['minDate'], fn (\$query, \$minDate) => \$query->where('created_at', '>=', \$minDate))
            ->when(\$this->filters['search'], function (\$query, \$search) {
                \$query->where('name', 'like', '%' . \$search . '%');
            });
    }

    /**
     * Get paginated rows
     */
    public function getRowsProperty() 
    { 
        return \$this->applyPagination(\$this->rowsQuery); 
    }

    /**
     * Render the component
     */
    public function render()
    {
        return view('livewire.$directoryName.$lowerName-register', [
            '{$lowerName}s' => \$this->rows,
        ]);
    }

    /**
     * Save or update record
     */
    public function save{$name}()
    {
        \$this->form->save();

        \$this->form->updateId = null;
        \$this->form->this_{$snakeName} = null;
    }

    /**
     * Edit a record
     * 
     * @param int \$id
     */
    public function edit(\$id)
    {
        \$this->form->setFields(\$id);
    }

    /**
     * Delete a record
     * 
     * @param {$name} \$id
     */
    public function delete({$name} \$id)
    {
        \$this->deleteIfNoChildren(\$id);
    }
    
    /**
     * Confirm deletion
     * 
     * @param int \$id
     */
    public function confirmDelete(\$id)
    {
        \$this->deleteModal = \$id;
    }
}
EOD;
    }

    /**
     * Generate view content
     * 
     * @param array $columns
     * @param string $lowerName
     * @param string $name
     * @return string
     */
    protected function generateViewContent(array $columns, string $lowerName, string $name): string
    {
        // Generate input fields based on UI framework and column types
        $inputFields = $this->generateInputFields($columns);
        
        // Generate table headers
        $tableHeaders = $this->generateTableHeaders($columns);
        
        // Generate table cells
        $tableCells = $this->generateTableCells($columns, $lowerName);
        
        // Return the complete view content based on UI framework
        if ($this->uiFramework === 'bootstrap') {
            return $this->generateBootstrapView($inputFields, $tableHeaders, $tableCells, $lowerName, $name);
        }
        
        // Default to Tailwind
        return $this->generateTailwindView($inputFields, $tableHeaders, $tableCells, $lowerName, $name);
    }

    /**
     * Generate input fields based on column types
     * 
     * @param array $columns
     * @return string
     */
    protected function generateInputFields(array $columns): string
    {
        if ($this->uiFramework === 'bootstrap') {
            return $this->generateBootstrapInputFields($columns);
        }
        
        // Default to Tailwind
        return $this->generateTailwindInputFields($columns);
    }

    /**
     * Generate Tailwind input fields
     * 
     * @param array $columns
     * @return string
     */
    protected function generateTailwindInputFields(array $columns): string
    {
        $inputFields = '';
        
        foreach ($columns as $column) {
            switch ($column['type']) {
                case 'text':
                    $inputFields .= '                <x-textarea label="{{ __(\'lang.' . $column['name'] . '\') }}" placeholder="{{ __(\'lang.' . $column['name'] . '\') }}" wire:model="form.' . $column['name'] . '" id="' . $column['name'] . '"/>' . "\n";
                    break;
                case 'date':
                    $inputFields .= '                <x-input type="date" label="{{ __(\'lang.' . $column['name'] . '\') }}" wire:model="form.' . $column['name'] . '" id="' . $column['name'] . '" />' . "\n";
                    break;
                case 'number':
                case 'decimal':
                case 'float':
                    $inputFields .= '                <x-input type="number" label="{{ __(\'lang.' . $column['name'] . '\') }}" wire:model="form.' . $column['name'] . '" id="' . $column['name'] . '" />' . "\n";
                    break;
                case 'boolean':
                    $inputFields .= '                <x-checkbox label="{{ __(\'lang.' . $column['name'] . '\') }}" wire:model="form.' . $column['name'] . '" id="' . $column['name'] . '" />' . "\n";
                    break;
                default:
                    $inputFields .= '                <x-input label="{{ __(\'lang.' . $column['name'] . '\') }}" placeholder="{{ __(\'lang.' . $column['name'] . '\') }}" wire:model="form.' . $column['name'] . '" id="' . $column['name'] . '" />' . "\n";
            }
        }
        
        return $inputFields;
    }

    /**
     * Generate Bootstrap input fields
     * 
     * @param array $columns
     * @return string
     */
    protected function generateBootstrapInputFields(array $columns): string
    {
        $inputFields = '';
        
        foreach ($columns as $column) {
            switch ($column['type']) {
                case 'text':
                    $inputFields .= '                <div class="mb-3">
                    <label for="' . $column['name'] . '" class="form-label">{{ __(\'lang.' . $column['name'] . '\') }}</label>
                    <textarea class="form-control @error(\'form.' . $column['name'] . '\') is-invalid @enderror" id="' . $column['name'] . '" wire:model="form.' . $column['name'] . '" placeholder="{{ __(\'lang.' . $column['name'] . '\') }}"></textarea>
                    @error(\'form.' . $column['name'] . '\') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>' . "\n";
                    break;
                case 'date':
                    $inputFields .= '                <div class="mb-3">
                    <label for="' . $column['name'] . '" class="form-label">{{ __(\'lang.' . $column['name'] . '\') }}</label>
                    <input type="date" class="form-control @error(\'form.' . $column['name'] . '\') is-invalid @enderror" id="' . $column['name'] . '" wire:model="form.' . $column['name'] . '">
                    @error(\'form.' . $column['name'] . '\') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>' . "\n";
                    break;
                case 'number':
                case 'decimal':
                case 'float':
                    $inputFields .= '                <div class="mb-3">
                    <label for="' . $column['name'] . '" class="form-label">{{ __(\'lang.' . $column['name'] . '\') }}</label>
                    <input type="number" class="form-control @error(\'form.' . $column['name'] . '\') is-invalid @enderror" id="' . $column['name'] . '" wire:model="form.' . $column['name'] . '">
                    @error(\'form.' . $column['name'] . '\') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>' . "\n";
                    break;
                case 'boolean':
                    $inputFields .= '                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input @error(\'form.' . $column['name'] . '\') is-invalid @enderror" id="' . $column['name'] . '" wire:model="form.' . $column['name'] . '">
                    <label class="form-check-label" for="' . $column['name'] . '">{{ __(\'lang.' . $column['name'] . '\') }}</label>
                    @error(\'form.' . $column['name'] . '\') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>' . "\n";
                    break;
                default:
                    $inputFields .= '                <div class="mb-3">
                    <label for="' . $column['name'] . '" class="form-label">{{ __(\'lang.' . $column['name'] . '\') }}</label>
                    <input type="text" class="form-control @error(\'form.' . $column['name'] . '\') is-invalid @enderror" id="' . $column['name'] . '" wire:model="form.' . $column['name'] . '" placeholder="{{ __(\'lang.' . $column['name'] . '\') }}">
                    @error(\'form.' . $column['name'] . '\') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>' . "\n";
            }
        }
        
        return $inputFields;
    }

    /**
     * Generate table headers
     * 
     * @param array $columns
     * @return string
     */
    protected function generateTableHeaders(array $columns): string
    {
        $headers = '';
        
        foreach ($columns as $column) {
            if ($this->uiFramework === 'bootstrap') {
                $headers .= '                            <th>{{ __(\'lang.' . $column['name'] . '\') }}</th>' . "\n";
            } else {
                // Tailwind
                $headers .= '                            <x-table.heading>{{ __(\'lang.' . $column['name'] . '\') }}</x-table.heading>' . "\n";
            }
        }
        
        return $headers;
    }

    /**
     * Generate table cells
     * 
     * @param array $columns
     * @param string $lowerName
     * @return string
     */
    protected function generateTableCells(array $columns, string $lowerName): string
    {
        $cells = '';
        
        foreach ($columns as $column) {
            if ($this->uiFramework === 'bootstrap') {
                $cells .= '                                <td>{{ $' . $lowerName . '->' . $column['name'] . ' }}</td>' . "\n";
            } else {
                // Tailwind
                $cells .= '                                <x-table.cell>{{ $' . $lowerName . '->' . $column['name'] . ' }}</x-table.cell>' . "\n";
            }
        }
        
        return $cells;
    }

    /**
     * Generate Tailwind view
     * 
     * @param string $inputFields
     * @param string $tableHeaders
     * @param string $tableCells
     * @param string $lowerName
     * @param string $name
     * @return string
     */
    protected function generateTailwindView(
        string $inputFields, 
        string $tableHeaders, 
        string $tableCells,
        string $lowerName,
        string $name
    ): string {
        return <<<EOD
<div class="py-4">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <x-card title='{{ __("lang.{$name}RegisterForm") }}'>
            <x-errors />

            <div class="grid items-center grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
{$inputFields}
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-end gap-x-3">
                    <x-button primary wire:click='save{$name}' label="{{ isset(\$form->updateId) ? __('lang.update') : __('lang.save') }}" class="p-2 text-white bg-blue-500 rounded" />
                </div>
            </x-slot>
        </x-card>

        <div class="mt-2">
            <x-card>
                <div class="flex flex-col items-center justify-between py-4 space-y-3 md:flex-row md:space-y-0 ">
                    <div class="flex w-full space-x-1 md:w-1/2 rtl:space-x-reverse">
                        <x-input right-icon="magnifying-glass" placeholder="{{ __('lang.search') }}" wire:model.live='filters.search' class="border-spacing-10" />
                        <x-button outline black wire:click="toggleShowFilters" right-icon="funnel" />
                    </div>
                    <div class="flex flex-col items-stretch justify-end flex-shrink-0 w-full space-y-2 md:w-auto md:flex-row md:space-y-0 md:items-center md:space-x-3">
                        <div class="flex items-center w-full space-x-3 md:w-auto rtl:space-x-reverse">
                             <div class="w-14 ltr:ml-1 rtl:ml-1">
                                <x-native-select :options="['10', '25', '50']" wire:model.live="perPage"  />
                            </div>
                            <x-dropdown width='22' class="">
                                <x-slot name="trigger">
                                    <x-button class="shrink-0 w-max" label="{{ __('lang.tb.bulkActions') }}" outline right-icon="chevron-down" />
                                </x-slot>
                                <x-dropdown.item onclick="confirm('Are you sure ?') || event.stopImmediatePropagation()" wire:click="deleteSelected" icon="trash" label="{{ __('lang.delete') }}" />
                                <x-dropdown.item wire:click="exportSelected" icon="arrow-down-tray" separator label="{{ __('lang.tb.export') }}" />
                            </x-dropdown>
                        </div>
                    </div>
                </div>

                <div>
                    @if (\$showFilters)
                    <div class="relative flex p-4 mb-2 bg-gray-200 rounded shadow-inner">
                        <div class="w-full pr-2 space-y-4">
                            <div class="grid grid-cols-1 gap-6 mb-10 sm:grid-cols-2">
                                <x-datetime-picker without-time placeholder="{{ __('lang.tb.from') }}" label="{{ __('lang.registerDate') }}" wire:model.live="filters.minDate" />
                                <x-datetime-picker without-time placeholder="{{ __('lang.tb.to') }}" label="{{ __('lang.tb.to') }}" wire:model.live="filters.maxDate" />
                            </div>
                            <div>
                                <x-button-link wire:click="resetFilters" class="absolute bottom-0 right-0 p-4 mt-5">
                                    {{ __("lang.tb.resetFilters") }}
                                </x-button-link>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

             <div class="flex-col space-y-4">
                <x-table class="border-none shadow-none">
                    <x-slot name='head' class="border-none">
                        <x-table.heading class="px-2 mx-0">#</x-table.heading>
{$tableHeaders}
                        <x-table.heading class='px-3'>{{__("lang.actions")}}</x-table.heading>
                    </x-slot>

                    <x-slot name="body" class="border-none">
                        @forelse (\${$lowerName}s as \${$lowerName})
                        <x-table.row wire:loading.class.delay='opacity-50' wire:key='row-{{ \${$lowerName}->id }}'>
                            <x-table.cell scope="row">{{ \${$lowerName}s->firstItem()+ \$loop->index }}</x-table.cell>
{$tableCells}
                            <x-table.cell class="px-4 py-3 space-x-4 rtl:space-x-reverse">
                                <x-button-link wire:click="edit({{\${$lowerName}->id}})">
                                    <x-icon name="pencil-square" class="w-4 h-4" />
                                </x-button-link>
                                <x-button-link wire:click='confirmDelete({{\${$lowerName}->id}})' class="text-red-700">
                                    <x-icon name="trash" class="w-4 h-4" />
                                </x-button-link>
                            </x-table.cell>
                        </x-table.row>
                        @empty
                        <x-table.row>
                            <x-table.cell colspan="10">
                                <div class="flex items-center justify-center space-x-2 rtl:space-x-reverse">
                                    <x-icon name="computer-desktop" solid class="w-8 h-8 text-gray-300" />
                                    <span class="py-8 text-xl font-medium text-gray-400">
                                        {{__('lang.no{$name}Found')}}
                                    </span>
                                </div>
                            </x-table.cell>
                        </x-table.row>
                        @endforelse
                    </x-slot>
                </x-table>
                <div>
                    {{ \${$lowerName}s->links() }}
                </div>
            </div>
            </x-card>
        </div>

        <x-modal wire:model="deleteModal">
            <div class="relative p-4 text-center bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
                <button wire:click='\$set("deleteModal", false)' type="button" class="text-gray-400 absolute top-2.5 right-2.5 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white">
                    <x-icon name="x-mark" class="w-5 h-5" />
                    <span class="sr-only">{{ __("lang.dm.closeModal") }}</span>
                </button>
                <x-icon name="trash" solid class="text-gray-400 dark:text-gray-500 w-11 h-11 mb-3.5 mx-auto" />
                <p class="mb-4 text-gray-500 dark:text-gray-300">{{ __("lang.dm.deleteConfirmationMessage") }}</p>
                <div class="flex items-center justify-center space-x-4 rtl:space-x-reverse">
                    <button wire:click='\$set("deleteModal", false)' type="button" class="btn-light">
                        {{ __('lang.dm.noCancel') }}
                    </button>
                    <x-button label="{{ __('lang.dm.yesImSure') }}" wire:click="delete({{\$deleteModal}})" negative />
                </div>
            </div>
        </x-modal>
    </div>
</div>
EOD;
    }

    /**
     * Generate Bootstrap view
     * 
     * @param string $inputFields
     * @param string $tableHeaders
     * @param string $tableCells
     * @param string $lowerName
     * @param string $name
     * @return string
     */
    protected function generateBootstrapView(
        string $inputFields, 
        string $tableHeaders, 
        string $tableCells,
        string $lowerName,
        string $name
    ): string {
        return <<<EOD
<div class="py-4">
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __("lang.{$name}RegisterForm") }}</h5>
            </div>
            <div class="card-body">
                @if (\$errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach (\$errors->all() as \$error)
                        <li>{{ \$error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="row g-3">
{$inputFields}
                </div>
            </div>
            <div class="card-footer text-end">
                <button wire:click='save{$name}' class="btn btn-primary">
                    {{ isset(\$form->updateId) ? __('lang.update') : __('lang.save') }}
                </button>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="{{ __('lang.search') }}" wire:model.live='filters.search'>
                            <button class="btn btn-outline-secondary" type="button" wire:click="toggleShowFilters">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <div class="d-flex justify-content-md-end align-items-center">
                            <select class="form-select me-2" style="width: auto;" wire:model.live="perPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    {{ __('lang.tb.bulkActions') }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" wire:click="deleteSelected" onclick="return confirm('Are you sure?')">{{ __('lang.delete') }}</a></li>
                                    <li><a class="dropdown-item" href="#" wire:click="exportSelected">{{ __('lang.tb.export') }}</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                @if (\$showFilters)
                <div class="bg-light p-3 mb-3 rounded">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('lang.registerDate') }} ({{ __('lang.tb.from') }})</label>
                            <input type="date" class="form-control" wire:model.live="filters.minDate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('lang.registerDate') }} ({{ __('lang.tb.to') }})</label>
                            <input type="date" class="form-control" wire:model.live="filters.maxDate">
                        </div>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-secondary" wire:click="resetFilters">
                            {{ __("lang.tb.resetFilters") }}
                        </button>
                    </div>
                </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
{$tableHeaders}
                                <th>{{ __("lang.actions") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (\${$lowerName}s as \${$lowerName})
                            <tr wire:key='row-{{ \${$lowerName}->id }}'>
                                <td>{{ \${$lowerName}s->firstItem() + \$loop->index }}</td>
{$tableCells}
                                <td>
                                    <button class="btn btn-sm btn-primary" wire:click="edit({{\${$lowerName}->id}})">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" wire:click='confirmDelete({{\${$lowerName}->id}})'>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <i class="bi bi-display fs-1 text-secondary"></i>
                                    <p class="fs-4 text-secondary mt-2">{{__('lang.no{$name}Found')}}</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div>
                    {{ \${$lowerName}s->links() }}
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog">
                <div class="modal-content">
                    @if(\$deleteModal)
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __("lang.dm.confirmDelete") }}</h5>
                        <button type="button" class="btn-close" wire:click='\$set("deleteModal", false)'></button>
                    </div>
                    <div class="modal-body text-center">
                        <i class="bi bi-trash fs-1 text-secondary mb-3"></i>
                        <p>{{ __("lang.dm.deleteConfirmationMessage") }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click='\$set("deleteModal", false)'>
                            {{ __('lang.dm.noCancel') }}
                        </button>
                        <button type="button" class="btn btn-danger" wire:click="delete({{\$deleteModal}})">
                            {{ __('lang.dm.yesImSure') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('livewire:initialized', function () {
                Livewire.on('showDeleteModal', () => {
                    new bootstrap.Modal(document.getElementById('deleteModal')).show();
                });
                Livewire.on('hideDeleteModal', () => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                    if (modal) modal.hide();
                });
            });
        </script>
    </div>
</div>
EOD;
    }
}