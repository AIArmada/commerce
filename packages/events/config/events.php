<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\Venue as VenueModel;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

$tablePrefix = env('EVENTS_TABLE_PREFIX', 'event_');
$productModel = Product::class;
$variantModel = Variant::class;
$customerModel = Customer::class;
$orderModel = Order::class;
$orderItemModel = OrderItem::class;

return [
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'jsonb')),
        'tables' => [
            'series' => env('EVENTS_TABLE_SERIES', $tablePrefix . 'series'),
            'events' => env('EVENTS_TABLE_EVENTS', 'events'),
            'speakers' => env('EVENTS_TABLE_SPEAKERS', $tablePrefix . 'speakers'),
            'venues' => env('EVENTS_TABLE_VENUES', $tablePrefix . 'venues'),
            'occurrences' => env('EVENTS_TABLE_OCCURRENCES', $tablePrefix . 'occurrences'),
            'registrations' => env('EVENTS_TABLE_REGISTRATIONS', $tablePrefix . 'registrations'),
        ],
    ],

    'models' => [
        'event' => EventModel::class,
        'organizer' => null,
        'speaker' => null,
        'venue' => VenueModel::class,
    ],

    'features' => [
        'owner' => [
            'enabled' => env('EVENTS_OWNER_ENABLED', false),
            'include_global' => env('EVENTS_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('EVENTS_OWNER_AUTO_ASSIGN', true),
        ],
    ],

    'codes' => [
        'registration_prefix' => env('EVENTS_REGISTRATION_PREFIX', 'REG'),
        'registration_length' => (int) env('EVENTS_REGISTRATION_LENGTH', 10),
    ],

    'defaults' => [
        'occurrence_participation_mode' => env('EVENTS_OCCURRENCE_PARTICIPATION_MODE', 'registration_required'),
        'event_moderation_status' => env('EVENTS_EVENT_MODERATION_STATUS', 'approved'),
        'event_visibility' => env('EVENTS_EVENT_VISIBILITY', 'public'),
    ],

    'media' => [
        'collections' => [
            'cover' => env('EVENTS_MEDIA_COLLECTION_COVER', 'cover'),
            'poster' => env('EVENTS_MEDIA_COLLECTION_POSTER', 'poster'),
            'gallery' => env('EVENTS_MEDIA_COLLECTION_GALLERY', 'gallery'),
        ],
    ],

    'taxonomy' => [
        'groups' => [
            'category',
            'topic',
            'audience',
            'language',
        ],
    ],

    'search' => [
        'payload_resolver' => null,
    ],

    'timezone' => [
        'default' => env('EVENTS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        'display_timezone_resolver' => null,
    ],

    'lifecycle' => [
        'occurrence' => [
            'registration_accepting_statuses' => ['scheduled', 'live'],
            'check_in_accepting_statuses' => ['scheduled', 'live'],
            'walk_in_accepting_statuses' => ['scheduled', 'live'],
        ],
        'registration' => [
            'check_in_allowed_statuses' => ['confirmed'],
            'capacity_blocking_statuses' => ['pending', 'confirmed', 'checked_in', 'no_show'],
            'terminal_statuses' => ['checked_in', 'cancelled', 'refunded', 'no_show'],
        ],
    ],

    'integrations' => [
        'product_model' => class_exists($productModel) ? $productModel : null,
        'variant_model' => class_exists($variantModel) ? $variantModel : null,
        'customer_model' => class_exists($customerModel) ? $customerModel : null,
        'order_model' => class_exists($orderModel) ? $orderModel : null,
        'order_item_model' => class_exists($orderItemModel) ? $orderItemModel : null,
        'order_item_fulfillment_resolver' => null,
    ],
];
