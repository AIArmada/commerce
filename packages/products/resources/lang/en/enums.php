<?php

declare(strict_types=1);

return [
    'type' => [
        'simple' => 'Simple Product',
        'configurable' => 'Configurable Product',
        'bundle' => 'Bundle',
        'digital' => 'Digital Product',
        'subscription' => 'Subscription',
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
];
