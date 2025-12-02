<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('JNT_TABLE_PREFIX', 'jnt_'),
        'json_column_type' => env('JNT_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment & Credentials
    |--------------------------------------------------------------------------
    */
    'environment' => env('JNT_ENVIRONMENT', 'local'),

    'api_account' => env('JNT_API_ACCOUNT',
        in_array(env('JNT_ENVIRONMENT', 'local'), ['local', 'testing', 'development'])
            ? '640826271705595946' : null
    ),

    'private_key' => env('JNT_PRIVATE_KEY',
        in_array(env('JNT_ENVIRONMENT', 'local'), ['local', 'testing', 'development'])
            ? '8e88c8477d4e4939859c560192fcafbc' : null
    ),

    'customer_code' => env('JNT_CUSTOMER_CODE'),
    'password' => env('JNT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | API URLs
    |--------------------------------------------------------------------------
    */
    'base_urls' => [
        'testing' => env('JNT_BASE_URL_TESTING', 'https://demoopenapi.jtexpress.my/webopenplatformapi'),
        'production' => env('JNT_BASE_URL_PRODUCTION', 'https://ylopenapi.jtexpress.my/webopenplatformapi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Register a resolver that returns the current owner (merchant, tenant, etc).
    | When enabled, orders are automatically scoped to the owner.
    |
    */
    'owner' => [
        'enabled' => env('JNT_OWNER_ENABLED', false),
        'resolver' => NullOwnerResolver::class,
        'include_global' => env('JNT_OWNER_INCLUDE_GLOBAL', true),
        'auto_assign_on_create' => env('JNT_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => env('JNT_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('JNT_HTTP_CONNECT_TIMEOUT', 10),
        'retry_times' => env('JNT_HTTP_RETRY_TIMES', 3),
        'retry_sleep' => env('JNT_HTTP_RETRY_SLEEP', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'url' => env('JNT_WEBHOOK_URL'),
    ],

    'webhooks' => [
        'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
        'route' => env('JNT_WEBHOOK_ROUTE', 'webhooks/jnt/status'),
        'middleware' => ['api', 'jnt.verify.signature'],
        'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('JNT_LOGGING_ENABLED', true),
        'channel' => env('JNT_LOGGING_CHANNEL', 'stack'),
    ],
];
