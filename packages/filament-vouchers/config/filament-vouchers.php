<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | Controls the navigation group label used by all Filament voucher
    | resources and widgets. Set to `null` to leave vouchers at the root level.
    |
    */
    'navigation_group' => 'E-commerce',

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Defines the default ISO-4217 currency code used when displaying monetary
    | values in widgets and resources. Individual records still store their
    | configured currency, this value is only a display fallback.
    |
    */
    'default_currency' => 'MYR',

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | Configure how frequently (in seconds) voucher tables should poll for
    | updates. Accepts either an integer (seconds) or a string supported by
    | Filament (e.g. "30s"). Use `null` to disable polling entirely.
    |
    */
    'polling_interval' => 60,

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Fine tune resource-specific behaviours such as navigation ordering.
    |
    */
    'resources' => [
        'navigation_sort' => [
            'vouchers' => 40,
            'voucher_usage' => 41,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner Type Definitions
    |--------------------------------------------------------------------------
    |
    | Define the owner types that can be assigned to vouchers from the Filament
    | form. Each entry should include the Eloquent model class, a human readable
    | label, and the attribute used for display. Example:
    |
    | 'owners' => [
    |     [
    |         'label' => 'Vendors',
    |         'model' => App\Models\Vendor::class,
    |         'title_attribute' => 'name',
    |         'subtitle_attribute' => 'email', // optional
    |         'search_attributes' => ['name', 'email'],
    |     ],
    | ],
    |
    | Leaving this array empty hides the owner selector fields, allowing global
    | vouchers only. The voucher package ownership resolver remains unchanged.
    |
    */
    'owners' => [],
];
