# Laravel Livewire CRUD Generator

A powerful Laravel package to quickly generate Livewire CRUD components for your Laravel application.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hawraz/livewire-crud-generator.svg?style=flat-square)](https://packagist.org/packages/hawraz/livewire-crud-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/hawraz/livewire-crud-generator.svg?style=flat-square)](https://packagist.org/packages/hawraz/livewire-crud-generator)

## Features

- Generate complete Livewire CRUD operations with a single command
- Support for migrations, models, Livewire forms, components, and routes
- Dynamic column definitions with types and validation rules
- Support for both Tailwind CSS and Bootstrap UI frameworks
- Customizable through configuration file
- Support for directory nesting and namespacing

## Installation

You can install the package via composer:

```bash
composer require hawraz/livewire-crud-generator
```

## Configuration

You can publish the configuration file using:

```bash
php artisan vendor:publish --provider="Hawraz\LivewireCrudGenerator\LivewireCrudGeneratorServiceProvider" --tag="config"
```

This will create a `config/livewire-crud-generator.php` file where you can modify the default settings.

## Usage

### Basic Usage

```bash
php artisan make:crud Post --columns="title:string:required|max:255,body:text:required|min:50,published:boolean:nullable"
```

This will generate:
- Migration for the `posts` table
- Post model
- PostForm Livewire form
- PostRegister Livewire component
- A route for the component

### Using Namespaces

```bash
php artisan make:crud Blog.Post --columns="title:string:required|max:255,body:text:required|min:50,published:boolean:nullable"
```

This will generate components in the `App\Livewire\Blog` namespace and views in the `resources/views/livewire/blog` directory.

### Available Options

| Option | Description |
|--------|-------------|
| `--columns` | Define the columns with their types and validation rules |
| `--migration` | Generate a migration file |
| `--model` | Generate a model file |
| `--form` | Generate a Livewire form |
| `--component` | Generate a Livewire component |
| `--route` | Add a route to the web.php file |
| `--modal` | Generate a Livewire modal component |
| `--force` | Force overwrite existing files |
| `--ui` | Specify the UI framework (tailwind, bootstrap) |
| `--except` | Exclude specific parts from generation |

If no options are specified, all parts will be generated.

### Excluding Specific Parts

```bash
php artisan make:crud Product --columns="name:string:required,price:decimal:required" --except="migration,model"
```

This will generate everything except the migration and model.

### Using Different UI Frameworks

```bash
php artisan make:crud Customer --columns="name:string:required,email:string:required|email" --ui=bootstrap
```

This will generate components using Bootstrap markup.

## Column Definition Format

The column definition format is:

```
name:type:validation_rules
```

Multiple columns are separated by commas:

```
name:string:required|max:255,email:string:required|email,age:integer:nullable|min:18
```

### Supported Column Types

- `string`
- `text`
- `integer`
- `bigInteger`
- `boolean`
- `date`
- `datetime`
- `time`
- `timestamp`
- `decimal`
- `double`
- `float`
- `json`
- `jsonb`

## Required Traits

This package depends on a few traits that should be created in your application:

### WithPerPagePagination.php

Create this file at `app/Traits/DataTable/WithPerPagePagination.php`:

```php
<?php

namespace App\Traits\DataTable;

trait WithPerPagePagination
{
    public $perPage = 10;

    public function applyPagination($query)
    {
        return $query->paginate($this->perPage);
    }
}
```

### WithSorting.php

Create this file at `app/Traits/DataTable/WithSorting.php`:

```php
<?php

namespace App\Traits\DataTable;

trait WithSorting
{
    public $sortField = 'id';
    public $sortDirection = 'desc';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }
}
```

### HandlesDeletion.php

Create this file at `app/Traits/HandlesDeletion.php`:

```php
<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait HandlesDeletion
{
    public function deleteIfNoChildren(Model $model)
    {
        try {
            $model->delete();
            session()->flash('message', 'Record deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Cannot delete record because it has related records.');
        }
    }
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email your email instead of using the issue tracker.

## Credits

- [Hawraz Nawzad](https://github.com/hawraz93)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.