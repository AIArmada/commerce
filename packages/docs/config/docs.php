<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    | Preferred JSON column type for future database tables that include JSON
    | data. This mirrors other packages and defaults to the global setting.
    */
    'database' => [
        'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Doc Types Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different doc types supported by the package.
    | Currently supports: invoices and receipts (more can be added).
    |
    */

    'types' => [
        'invoice' => [
            'default_template' => 'doc-default',
            'number_format' => [
                'prefix' => 'INV',
                'year_format' => 'y',
                'separator' => '-',
                'suffix_length' => 6,
            ],
            'storage' => [
                'disk' => env('DOCS_STORAGE_DISK', 'local'),
                'path' => env('DOCS_STORAGE_PATH', 'docs/invoices'),
            ],
            'defaults' => [
                'currency' => env('DOCS_CURRENCY', 'MYR'),
                'tax_rate' => env('DOCS_TAX_RATE', 0),
                'due_days' => env('DOCS_DUE_DAYS', 30),
            ],
        ],
        'receipt' => [
            'default_template' => 'doc-default',
            'number_format' => [
                'prefix' => 'RCP',
                'year_format' => 'y',
                'separator' => '-',
                'suffix_length' => 6,
            ],
            'storage' => [
                'disk' => env('DOCS_STORAGE_DISK', 'local'),
                'path' => env('DOCS_STORAGE_PATH', 'docs/receipts'),
            ],
            'defaults' => [
                'currency' => env('DOCS_CURRENCY', 'MYR'),
            ],
        ],
        'ticket' => [
            'default_template' => 'doc-default',
            'number_format' => [
                'prefix' => 'TKT',
                'year_format' => 'y',
                'separator' => '-',
                'suffix_length' => 6,
            ],
            'storage' => [
                'disk' => env('DOCS_STORAGE_DISK', 'local'),
                'path' => env('DOCS_STORAGE_PATH', 'docs/tickets'),
            ],
            'defaults' => [
                'currency' => env('DOCS_CURRENCY', 'MYR'),
                'due_days' => 0,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF generation via Spatie Laravel PDF
    |
    */
    'pdf' => [
        'format' => 'a4',
        'orientation' => 'portrait',
        'margin' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Default company information to display on docs. This can be
    | overridden when creating a doc.
    |
    */
    'company' => [
        'name' => env('DOCS_COMPANY_NAME', config('app.name')),
        'address' => env('DOCS_COMPANY_ADDRESS'),
        'city' => env('DOCS_COMPANY_CITY'),
        'state' => env('DOCS_COMPANY_STATE'),
        'postal_code' => env('DOCS_COMPANY_POSTAL_CODE'),
        'country' => env('DOCS_COMPANY_COUNTRY'),
        'phone' => env('DOCS_COMPANY_PHONE'),
        'email' => env('DOCS_COMPANY_EMAIL'),
        'website' => env('DOCS_COMPANY_WEBSITE'),
        'tax_id' => env('DOCS_COMPANY_TAX_ID'),
    ],
];
