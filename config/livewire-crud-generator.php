<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default UI Framework
    |--------------------------------------------------------------------------
    |
    | This value determines which UI framework will be used by default when
    | generating views. Supported values: 'tailwind', 'bootstrap'
    |
    */
    'ui_framework' => 'tailwind',

    /*
    |--------------------------------------------------------------------------
    | Model Paths
    |--------------------------------------------------------------------------
    |
    | This value determines where models are stored.
    |
    */
    'model_path' => app_path('Models'),

    /*
    |--------------------------------------------------------------------------
    | Form Paths
    |--------------------------------------------------------------------------
    |
    | This value determines where Livewire forms are stored.
    |
    */
    'form_path' => app_path('Livewire/Forms'),

    /*
    |--------------------------------------------------------------------------
    | Default Add Traits
    |--------------------------------------------------------------------------
    |
    | This array holds the traits that will be added to generated models.
    |
    */
    'model_traits' => [
        'HasFactory',
        'SoftDeletes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Add User Relationship
    |--------------------------------------------------------------------------
    |
    | This determines if the migration and model should include a relationship
    | to the User model.
    |
    */
    'add_user_relationship' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Table Structure
    |--------------------------------------------------------------------------
    |
    | This determines if soft delete columns should be added to the migration.
    |
    */
    'use_soft_delete' => true,
];