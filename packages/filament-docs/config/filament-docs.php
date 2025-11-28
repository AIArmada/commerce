<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | The navigation group where the docs resources will be displayed.
    |
    */
    'navigation_group' => 'Documents',

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Configure navigation sort order for Filament resources.
    |
    */
    'resources' => [
        /*
        | Navigation sort order for resources
        */
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Preview
    |--------------------------------------------------------------------------
    |
    | Enable PDF preview in the document view page.
    |
    */
    'enable_pdf_preview' => true,

    /*
    |--------------------------------------------------------------------------
    | Auto Generate PDF
    |--------------------------------------------------------------------------
    |
    | Automatically generate PDF when a document is created from Filament.
    |
    */
    'auto_generate_pdf' => true,
];
