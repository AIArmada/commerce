<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Define the database table names used by the customers package.
    |
    */

    'tables' => [
        'customers' => 'customers',
        'addresses' => 'customer_addresses',
        'segments' => 'customer_segments',
        'segment_customer' => 'customer_segment_customer',
        'groups' => 'customer_groups',
        'group_members' => 'customer_group_members',
        'wishlists' => 'wishlists',
        'wishlist_items' => 'wishlist_items',
        'notes' => 'customer_notes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database JSON Column Type
    |--------------------------------------------------------------------------
    */
    'json_column_type' => 'json',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model that represents a customer. This should be your
    | application's User model that uses the HasCustomerProfile trait.
    |
    */

    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Address Types
    |--------------------------------------------------------------------------
    |
    | Available address types that customers can assign to their addresses.
    |
    */

    'address_types' => [
        'billing' => 'Billing Address',
        'shipping' => 'Shipping Address',
        'both' => 'Billing & Shipping',
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Segments
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic customer segmentation.
    |
    */

    'segments' => [
        // Enable automatic segment assignment
        'auto_assign' => true,

        // How often to recalculate segments (in hours)
        'recalculate_interval' => 24,

        // Built-in segment types
        'types' => [
            'loyalty' => 'Customer Loyalty Tier',
            'behavior' => 'Purchasing Behavior',
            'demographic' => 'Demographics',
            'custom' => 'Custom Segment',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Store Credit / Wallet
    |--------------------------------------------------------------------------
    |
    | Configuration for customer wallet and store credit.
    |
    */

    'wallet' => [
        'enabled' => true,
        'currency' => 'MYR',
        'max_balance' => 100000_00, // In cents: RM 100,000
        'min_topup' => 10_00, // In cents: RM 10
    ],

    /*
    |--------------------------------------------------------------------------
    | Wishlists
    |--------------------------------------------------------------------------
    |
    | Configuration for customer wishlists.
    |
    */

    'wishlists' => [
        'enabled' => true,
        'max_per_customer' => 10,
        'max_items_per_wishlist' => 100,
        'allow_public' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR Compliance
    |--------------------------------------------------------------------------
    |
    | Settings for GDPR and data privacy compliance.
    |
    */

    'privacy' => [
        // Allow customers to request data export
        'data_export' => true,

        // Allow customers to request account deletion
        'account_deletion' => true,

        // Days to retain data after deletion request
        'retention_days' => 30,

        // Anonymize instead of hard delete
        'anonymize' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Tracking
    |--------------------------------------------------------------------------
    |
    | Track customer activity for insights.
    |
    */

    'activity' => [
        'enabled' => true,
        'track_logins' => true,
        'track_orders' => true,
        'track_page_views' => false, // Requires frontend integration
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Groups (B2B)
    |--------------------------------------------------------------------------
    |
    | Configuration for business/team customer groups.
    |
    */

    'groups' => [
        'enabled' => true,
        'max_members' => 50,
        'require_approval' => true,
    ],

];
