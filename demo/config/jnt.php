<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | J&T Express API Credentials
    |--------------------------------------------------------------------------
    */
    'customer_code' => env('JNT_CUSTOMER_CODE', 'DEMO123'),
    'password' => env('JNT_PASSWORD', 'demo_password'),
    'api_key' => env('JNT_API_KEY', 'demo_api_key'),
    'api_account' => env('JNT_API_ACCOUNT', 'demo_account'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */
    'sandbox' => env('JNT_SANDBOX', true),
    'base_url' => env('JNT_BASE_URL', 'https://test-jts3openapi.jtexpress.my'),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => 'jnt_',
        'tables' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Sender (Shipper) Details
    |--------------------------------------------------------------------------
    */
    'sender' => [
        'name' => env('JNT_SENDER_NAME', 'AIArmada Commerce'),
        'phone' => env('JNT_SENDER_PHONE', '+60123456789'),
        'address' => env('JNT_SENDER_ADDRESS', 'Lot 15, Jalan Perusahaan 2'),
        'city' => env('JNT_SENDER_CITY', 'Shah Alam'),
        'state' => env('JNT_SENDER_STATE', 'Selangor'),
        'postcode' => env('JNT_SENDER_POSTCODE', '40150'),
        'country' => env('JNT_SENDER_COUNTRY', 'MY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'secret' => env('JNT_WEBHOOK_SECRET'),
        'verify_signature' => env('JNT_WEBHOOK_VERIFY', false),
    ],
];
