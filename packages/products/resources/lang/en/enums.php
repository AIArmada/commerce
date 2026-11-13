<?php

declare(strict_types=1);

return [
    'type' => [
        'simple' => 'Simple Product',
        'configurable' => 'Configurable Product',
        'composite' => 'Composite Package',
        'digital' => 'Digital Product',
        'subscription' => 'Subscription',
    ],
    'composite_price_mode' => [
        'fixed' => 'Fixed package price',
        'sum' => 'Sum of component prices',
    ],
    'status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'disabled' => 'Disabled',
        'archived' => 'Archived',
    ],
    'visibility' => [
        'catalog' => 'Catalog only',
        'search' => 'Search only',
        'catalog_search' => 'Catalog & Search',
        'individual' => 'Individual (not listed)',
        'hidden' => 'Hidden',
    ],
    'attribute_type' => [
        'text' => 'Text',
        'textarea' => 'Textarea',
        'number' => 'Number',
        'boolean' => 'Yes/No',
        'select' => 'Dropdown',
        'multiselect' => 'Multi-select',
        'date' => 'Date',
        'color' => 'Color',
        'media' => 'Media',
    ],
];
