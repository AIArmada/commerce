<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventSubLocation;
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
            'people' => env('EVENTS_TABLE_PEOPLE', $tablePrefix . 'speakers'),
            'speakers' => env('EVENTS_TABLE_SPEAKERS', $tablePrefix . 'speaker_profiles'),
            'organizers' => env('EVENTS_TABLE_ORGANIZERS', $tablePrefix . 'organizers'),
            'sponsors' => env('EVENTS_TABLE_SPONSORS', $tablePrefix . 'sponsors'),
            'venues' => env('EVENTS_TABLE_VENUES', $tablePrefix . 'venues'),
            'occurrences' => env('EVENTS_TABLE_OCCURRENCES', $tablePrefix . 'occurrences'),
            'sub_locations' => env('EVENTS_TABLE_SUB_LOCATIONS', $tablePrefix . 'sub_locations'),
            'registrations' => env('EVENTS_TABLE_REGISTRATIONS', $tablePrefix . 'registrations'),
            'classifications' => env('EVENTS_TABLE_CLASSIFICATIONS', $tablePrefix . 'classifications'),
            'assets' => env('EVENTS_TABLE_ASSETS', $tablePrefix . 'assets'),
            'references' => env('EVENTS_TABLE_REFERENCES', $tablePrefix . 'reference_assignments'),
            'submissions' => env('EVENTS_TABLE_SUBMISSIONS', $tablePrefix . 'submissions'),
            'reviews' => env('EVENTS_TABLE_REVIEWS', $tablePrefix . 'reviews'),
            'change_notices' => env('EVENTS_TABLE_CHANGE_NOTICES', $tablePrefix . 'change_notices'),
            'agenda_items' => env('EVENTS_TABLE_AGENDA_ITEMS', $tablePrefix . 'agenda_items'),
            'attendance' => env('EVENTS_TABLE_ATTENDANCE', $tablePrefix . 'attendance'),
            'engagements' => env('EVENTS_TABLE_ENGAGEMENTS', $tablePrefix . 'engagements'),
            'seat_categories' => env('EVENTS_TABLE_SEAT_CATEGORIES', $tablePrefix . 'seat_categories'),
            'registration_groups' => env('EVENTS_TABLE_REGISTRATION_GROUPS', $tablePrefix . 'registration_groups'),
        ],
    ],

    'models' => [
        'event' => EventModel::class,
        'organizer' => null,
        'sub_location' => EventSubLocation::class,
    ],

    'addresses' => [
        'models' => [
            VenueModel::class,
        ],
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
        'event_structure' => env('EVENTS_EVENT_STRUCTURE', 'standalone'),
        'occurrence_schedule_mode' => env('EVENTS_OCCURRENCE_SCHEDULE_MODE', 'manual'),
        'occurrence_registration_mode' => env('EVENTS_OCCURRENCE_REGISTRATION_MODE', 'free'),
        'occurrence_duplicate_strategy' => env('EVENTS_OCCURRENCE_DUPLICATE_STRATEGY', 'per_occurrence'),
    ],

    'moderation' => [
        'actions' => [
            'submit' => [
                'from' => ['draft', 'pending', 'approved', 'changes_requested', 'rejected'],
                'to' => 'pending',
                'note_required' => false,
                'reason_required' => false,
            ],
            'approve' => [
                'from' => ['pending', 'changes_requested'],
                'to' => 'approved',
                'note_required' => false,
                'reason_required' => false,
            ],
            'request_changes' => [
                'from' => ['pending', 'approved'],
                'to' => 'changes_requested',
                'note_required' => true,
                'reason_required' => true,
            ],
            'reject' => [
                'from' => ['pending', 'approved', 'changes_requested'],
                'to' => 'rejected',
                'note_required' => true,
                'reason_required' => true,
            ],
            'cancel' => [
                'from' => ['pending', 'approved', 'changes_requested', 'rejected'],
                'to' => 'pending',
                'note_required' => false,
                'reason_required' => false,
            ],
            'reconsider' => [
                'from' => ['rejected', 'changes_requested'],
                'to' => 'pending',
                'note_required' => false,
                'reason_required' => false,
            ],
            'revert_to_draft' => [
                'from' => ['pending', 'approved', 'changes_requested', 'rejected'],
                'to' => 'pending',
                'note_required' => false,
                'reason_required' => false,
            ],
            'remoderate' => [
                'from' => ['approved', 'changes_requested', 'rejected'],
                'to' => 'pending',
                'note_required' => false,
                'reason_required' => false,
            ],
        ],
        'reason_codes' => [
            'approved_for_publish' => [
                'label' => 'Approved for Publish',
                'note_required' => false,
            ],
            'needs_more_information' => [
                'label' => 'Needs More Information',
                'note_required' => true,
            ],
            'policy_violation' => [
                'label' => 'Policy Violation',
                'note_required' => true,
            ],
            'duplicate' => [
                'label' => 'Duplicate',
                'note_required' => true,
            ],
        ],
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
        'engine' => null,
    ],

    'classifications' => [
        'resolver' => null,
    ],

    'assets' => [
        'resolver' => null,
    ],

    'references' => [
        'resolver' => null,
    ],

    'timezone' => [
        'default' => env('EVENTS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        'display_timezone_resolver' => null,
    ],

    'slug' => [
        'source_field' => env('EVENTS_SLUG_SOURCE_FIELD', 'name'),
        'max_length' => (int) env('EVENTS_SLUG_MAX_LENGTH', 60),
    ],

    'schedule' => [
        'resolver' => null,
    ],

    'change_notices' => [
        'audience_resolver' => null,
        'notification_dispatcher' => null,
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
            'auto_promote_waitlist' => env('EVENTS_AUTO_PROMOTE_WAITLIST', false),
        ],
    ],

    'integrations' => [
        'product_model' => class_exists($productModel) ? $productModel : null,
        'variant_model' => class_exists($variantModel) ? $variantModel : null,
        'customer_model' => class_exists($customerModel) ? $customerModel : null,
        'order_model' => class_exists($orderModel) ? $orderModel : null,
        'order_item_model' => class_exists($orderItemModel) ? $orderItemModel : null,
        'checkout_intent_resolver' => null,
        'order_item_fulfillment_resolver' => null,
    ],
];
