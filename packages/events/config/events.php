<?php

declare(strict_types=1);
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

$tablePrefix = env('EVENTS_TABLE_PREFIX', '');
$ownerConfig = [
    'enabled' => env('EVENTS_OWNER_ENABLED', true),
    'include_global' => env('EVENTS_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('EVENTS_OWNER_AUTO_ASSIGN', true),
];

return [

    /* Database */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('EVENTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'jsonb')),
        'tables' => [
            'events' => env('EVENTS_TABLE_EVENTS', $tablePrefix . 'events'),
            'event_occurrences' => env('EVENTS_TABLE_OCCURRENCES', $tablePrefix . 'event_occurrences'),
            'event_sessions' => env('EVENTS_TABLE_SESSIONS', $tablePrefix . 'event_sessions'),
            'venues' => env('EVENTS_TABLE_VENUES', $tablePrefix . 'venues'),
            'venue_spaces' => env('EVENTS_TABLE_VENUE_SPACES', $tablePrefix . 'venue_spaces'),
            'venue_space_types' => env('EVENTS_TABLE_VENUE_SPACE_TYPES', $tablePrefix . 'venue_space_types'),
            'event_locations' => env('EVENTS_TABLE_LOCATIONS', $tablePrefix . 'event_locations'),
            'facility_types' => env('EVENTS_TABLE_FACILITY_TYPES', $tablePrefix . 'facility_types'),
            'venue_facilities' => env('EVENTS_TABLE_VENUE_FACILITIES', $tablePrefix . 'venue_facilities'),
            'event_facilities' => env('EVENTS_TABLE_EVENT_FACILITIES', $tablePrefix . 'event_facilities'),
            'event_roles' => env('EVENTS_TABLE_ROLES', $tablePrefix . 'event_roles'),
            'event_involvements' => env('EVENTS_TABLE_INVOLVEMENTS', $tablePrefix . 'event_involvements'),
            'event_access_policies' => env('EVENTS_TABLE_ACCESS_POLICIES', $tablePrefix . 'event_access_policies'),
            'event_registrations' => env('EVENTS_TABLE_REGISTRATIONS', $tablePrefix . 'event_registrations'),
            'event_registration_participants' => env('EVENTS_TABLE_REGISTRATION_PARTICIPANTS', $tablePrefix . 'event_registration_participants'),
            'event_registration_answers' => env('EVENTS_TABLE_REGISTRATION_ANSWERS', $tablePrefix . 'event_registration_answers'),
            'event_registration_items' => env('EVENTS_TABLE_REGISTRATION_ITEMS', $tablePrefix . 'event_registration_items'),
            'event_ticket_types' => env('EVENTS_TABLE_TICKET_TYPES', $tablePrefix . 'event_ticket_types'),
            'event_ticket_type_components' => env('EVENTS_TABLE_TICKET_TYPE_COMPONENTS', $tablePrefix . 'event_ticket_type_components'),
            'event_ticket_type_seating_options' => env('EVENTS_TABLE_TICKET_TYPE_SEATING_OPTIONS', $tablePrefix . 'event_ticket_type_seating_options'),
            'event_passes' => env('EVENTS_TABLE_PASSES', $tablePrefix . 'event_passes'),
            'event_seat_maps' => env('EVENTS_TABLE_SEAT_MAPS', $tablePrefix . 'event_seat_maps'),
            'event_seat_sections' => env('EVENTS_TABLE_SEAT_SECTIONS', $tablePrefix . 'event_seat_sections'),
            'event_seats' => env('EVENTS_TABLE_SEATS', $tablePrefix . 'event_seats'),
            'event_seat_holds' => env('EVENTS_TABLE_SEAT_HOLDS', $tablePrefix . 'event_seat_holds'),
            'event_seat_allocations' => env('EVENTS_TABLE_SEAT_ALLOCATIONS', $tablePrefix . 'event_seat_allocations'),
            'event_attendances' => env('EVENTS_TABLE_ATTENDANCES', $tablePrefix . 'event_attendances'),
            'event_attendance_logs' => env('EVENTS_TABLE_ATTENDANCE_LOGS', $tablePrefix . 'event_attendance_logs'),
            'event_materials' => env('EVENTS_TABLE_MATERIALS', $tablePrefix . 'event_materials'),
            'event_references' => env('EVENTS_TABLE_REFERENCES', $tablePrefix . 'event_references'),
            'event_links' => env('EVENTS_TABLE_LINKS', $tablePrefix . 'event_links'),
            'event_media' => env('EVENTS_TABLE_MEDIA', $tablePrefix . 'event_media'),
            'event_languages' => env('EVENTS_TABLE_LANGUAGES', $tablePrefix . 'event_languages'),
            'event_audiences' => env('EVENTS_TABLE_AUDIENCES', $tablePrefix . 'event_audiences'),
            'event_audience_profiles' => env('EVENTS_TABLE_AUDIENCE_PROFILES', $tablePrefix . 'event_audience_profiles'),
            'event_eligibility_rules' => env('EVENTS_TABLE_ELIGIBILITY_RULES', $tablePrefix . 'event_eligibility_rules'),
            'event_taxonomies' => env('EVENTS_TABLE_TAXONOMIES', $tablePrefix . 'event_taxonomies'),
            'event_terms' => env('EVENTS_TABLE_TERMS', $tablePrefix . 'event_terms'),
            'event_classifications' => env('EVENTS_TABLE_CLASSIFICATIONS', $tablePrefix . 'event_classifications'),
            'event_time_expressions' => env('EVENTS_TABLE_TIME_EXPRESSIONS', $tablePrefix . 'event_time_expressions'),
            'event_itineraries' => env('EVENTS_TABLE_ITINERARIES', $tablePrefix . 'event_itineraries'),
            'event_itinerary_items' => env('EVENTS_TABLE_ITINERARY_ITEMS', $tablePrefix . 'event_itinerary_items'),
            'event_series' => env('EVENTS_TABLE_SERIES', $tablePrefix . 'event_series'),
            'event_series_items' => env('EVENTS_TABLE_SERIES_ITEMS', $tablePrefix . 'event_series_items'),
            'event_series_rules' => env('EVENTS_TABLE_SERIES_RULES', $tablePrefix . 'event_series_rules'),
            'event_change_logs' => env('EVENTS_TABLE_CHANGE_LOGS', $tablePrefix . 'event_change_logs'),
            'event_updates' => env('EVENTS_TABLE_UPDATES', $tablePrefix . 'event_updates'),
            'event_update_items' => env('EVENTS_TABLE_UPDATE_ITEMS', $tablePrefix . 'event_update_items'),
            'event_notification_batches' => env('EVENTS_TABLE_NOTIFICATION_BATCHES', $tablePrefix . 'event_notification_batches'),
            'event_notification_deliveries' => env('EVENTS_TABLE_NOTIFICATION_DELIVERIES', $tablePrefix . 'event_notification_deliveries'),
            'event_submissions' => env('EVENTS_TABLE_SUBMISSIONS', $tablePrefix . 'event_submissions'),
            'event_submission_logs' => env('EVENTS_TABLE_SUBMISSION_LOGS', $tablePrefix . 'event_submission_logs'),
            'event_submission_attachments' => env('EVENTS_TABLE_SUBMISSION_ATTACHMENTS', $tablePrefix . 'event_submission_attachments'),
            'event_approval_requests' => env('EVENTS_TABLE_APPROVAL_REQUESTS', $tablePrefix . 'event_approval_requests'),
            'event_management_assignments' => env('EVENTS_TABLE_MANAGEMENT_ASSIGNMENTS', $tablePrefix . 'event_management_assignments'),
            'event_attributes' => env('EVENTS_TABLE_ATTRIBUTES', $tablePrefix . 'event_attributes'),
            'event_revisions' => env('EVENTS_TABLE_REVISIONS', $tablePrefix . 'event_revisions'),
            'event_search_documents' => env('EVENTS_TABLE_SEARCH_DOCUMENTS', $tablePrefix . 'event_search_documents'),
            'event_recurrence_rules' => env('EVENTS_TABLE_RECURRENCE_RULES', $tablePrefix . 'event_recurrence_rules'),
            'event_availability_blocks' => env('EVENTS_TABLE_AVAILABILITY_BLOCKS', $tablePrefix . 'event_availability_blocks'),
            'event_templates' => env('EVENTS_TABLE_TEMPLATES', $tablePrefix . 'event_templates'),
            'event_template_items' => env('EVENTS_TABLE_TEMPLATE_ITEMS', $tablePrefix . 'event_template_items'),

            // Module 8: Data Quality & Verification
            'event_verifications' => env('EVENTS_TABLE_VERIFICATIONS', $tablePrefix . 'event_verifications'),

            // Module 9: Moderation & Reporting
            'event_reports' => env('EVENTS_TABLE_REPORTS', $tablePrefix . 'event_reports'),
            'event_moderation_actions' => env('EVENTS_TABLE_MODERATION_ACTIONS', $tablePrefix . 'event_moderation_actions'),
        ],
    ],

    /* Features / Behavior */
    'features' => [
        'owner' => $ownerConfig,
    ],

    /* Owner / Multi-tenancy */
    'owner' => $ownerConfig,

    /* Defaults */
    'defaults' => [
        'timezone' => env('EVENTS_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    ],

    /* Shares */
    'shares' => [
        'route_name' => env('EVENTS_SHARE_ROUTE', 'events.show'),
    ],

    /* Codes */
    'codes' => [
        'registration_prefix' => env('EVENTS_REGISTRATION_PREFIX', 'REG'),
        'registration_length' => (int) env('EVENTS_REGISTRATION_LENGTH', 10),
    ],

    /* Lifecycle configuration */
    'lifecycle' => [
        'occurrence' => [
            'registration_accepting_statuses' => ['scheduled', 'published', 'live'],
            'check_in_accepting_statuses' => ['scheduled', 'published', 'live'],
            'walk_in_accepting_statuses' => ['scheduled', 'published', 'live'],
        ],
        'registration' => [
            'check_in_allowed_statuses' => ['confirmed'],
            'capacity_blocking_statuses' => ['pending', 'confirmed', 'checked_in', 'no_show'],
            'terminal_statuses' => ['checked_in', 'cancelled', 'refunded', 'no_show'],
            'auto_promote_waitlist' => env('EVENTS_AUTO_PROMOTE_WAITLIST', false),
        ],
    ],

    /* Resolvers (extensibility seams) */
    'classifications' => ['resolver' => null],
    'assets' => ['resolver' => null],
    'references' => ['resolver' => null],
    'timezone' => ['display_timezone_resolver' => null],
    'schedule' => ['resolver' => null],
    'search' => ['payload_resolver' => null, 'engine' => null],

    'change_notices' => [
        'audience_resolver' => null,
        'notification_dispatcher' => null,
    ],

    'moderation' => [
        'actions' => [
            'submit' => ['from' => ['draft', 'pending', 'approved', 'changes_requested', 'rejected'], 'to' => 'pending', 'note_required' => false, 'reason_required' => false],
            'approve' => ['from' => ['pending', 'changes_requested'], 'to' => 'approved', 'note_required' => false, 'reason_required' => false],
            'request_changes' => ['from' => ['pending', 'approved'], 'to' => 'changes_requested', 'note_required' => true, 'reason_required' => true],
            'reject' => ['from' => ['pending', 'approved', 'changes_requested'], 'to' => 'rejected', 'note_required' => true, 'reason_required' => true],
            'cancel' => ['from' => ['pending', 'approved', 'changes_requested', 'rejected'], 'to' => 'pending', 'note_required' => false, 'reason_required' => false],
            'reconsider' => ['from' => ['rejected', 'changes_requested'], 'to' => 'pending', 'note_required' => false, 'reason_required' => false],
        ],
        'reason_codes' => [
            'approved_for_publish' => ['label' => 'Approved for Publish', 'note_required' => false],
            'needs_more_information' => ['label' => 'Needs More Information', 'note_required' => true],
            'policy_violation' => ['label' => 'Policy Violation', 'note_required' => true],
            'duplicate' => ['label' => 'Duplicate', 'note_required' => true],
        ],
    ],

    /* Integrations */
    'integrations' => [
        'product_model' => class_exists(Product::class) ? Product::class : null,
        'variant_model' => class_exists(Variant::class) ? Variant::class : null,
        'customer_model' => class_exists(Customer::class) ? Customer::class : null,
        'order_model' => class_exists(Order::class) ? Order::class : null,
        'order_item_model' => class_exists(OrderItem::class) ? OrderItem::class : null,
        'checkout_intent_resolver' => null,
        'order_item_fulfillment_resolver' => null,
    ],

];
