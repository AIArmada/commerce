---
title: Configuration
---

# Configuration

## Database

```php
'database' => [
    'table_prefix' => '',
    'json_column_type' => 'jsonb',
    'tables' => [
        'batches' => 'communication_batches',
        'threads' => 'communication_threads',
        'communications' => 'communications',
        'recipients' => 'communication_recipients',
        'contents' => 'communication_contents',
        'deliveries' => 'communication_deliveries',
        'attempts' => 'communication_attempts',
        'events' => 'communication_events',
        'templates' => 'communication_templates',
        'template_versions' => 'communication_template_versions',
        'preferences' => 'communication_preferences',
        'suppressions' => 'communication_suppressions',
        'attachments' => 'communication_attachments',
        'references' => 'communication_references',
        'tracking_tokens' => 'communication_tracking_tokens',
        'destinations' => 'communication_destinations',
        'notification_inboxes' => 'notification_inboxes',
    ],
],
```

- `database.tables.destinations` stores per-recipient channel addresses used by `CommunicationDestinationResolver`

## Features

```php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
    'native_capture' => true,
    'auto_capture' => false,
    'auto_capture_allowlist' => [],
    'auto_capture_denylist' => [],
    'auto_capture_ignored_channels' => [],
],
```

- `owner.enabled` - enable owner scoping for tenant-owned records
- `owner.include_global` - include global rows when scoping queries
- `owner.auto_assign_on_create` - auto-fill the current owner on create
- `native_capture` - observe Laravel Notification events
- `auto_capture` - infer communications from native notifications
- `auto_capture_allowlist` - restrict auto capture to matching classes
- `auto_capture_denylist` - exclude matching classes from auto capture
- `auto_capture_ignored_channels` - skip selected notification channels

## Destinations

```php
'database' => [
    'tables' => [
        'destinations' => 'communication_destinations',
    ],
],
```

- `CommunicationDestination` rows are owner-scoped polymorphic records (`recipient_type` / `recipient_id`) with `channel`, optional `address` / `external_id`, `status`, `is_primary`, and `verified_at`
- The default `DestinationResolver` binding is `CommunicationDestinationResolver`: it prefers an active primary destination for the channel, then falls back to Laravel notifiable routing (`routeNotificationFor` / `routeNotificationFor{Driver}`)
- Bind `AIArmada\Communications\Contracts\DestinationResolver` to `NotifiableDestinationResolver` (or a custom implementation) if you want notifiable-only resolution without the destinations table

## Preferences

Preference rows support optional scoping beyond channel and category:

| Column | Purpose |
| --- | --- |
| `scope_type` | Logical scope kind (for example event, product, or campaign) |
| `scope_key` | Scope identifier within that kind |

Use these columns when a recipient should opt in or out for a narrower surface than the whole category.

## Inbox

```php
'database' => [
    'tables' => [
        'notification_inboxes' => 'notification_inboxes',
    ],
],
```

- `database.tables.notification_inboxes` stores the inbox rows used by `HasInbox`, `DispatchInboxNotificationAction`, and the inbox Livewire screen

## Integrations

Optional integrations can be enabled explicitly when the package is installed:

```php
'integrations' => [
    'activitylog' => [
        'enabled' => false,
    ],
    'laravel_auditing' => [
        'enabled' => false,
    ],
],
```

- `activitylog.enabled` - bind the activitylog audit recorder
- `laravel_auditing.enabled` - enable the `AuditableCommunication` trait checks

## HTTP

```php
'http' => [
    'route_prefix' => 'communications',
],
```

## Webhooks

```php
use AIArmada\Communications\Http\Middleware\VerifyWebhookSignature;

'webhooks' => [
    'middleware' => ['api', VerifyWebhookSignature::class],
    'providers' => [
        'provider-name' => [
            'secret' => env('PROVIDER_WEBHOOK_SECRET'),
        ],
    ],
    'route_name_prefix' => 'communications.webhooks.',
],
```

The default middleware fails closed unless the provider has a secret and sends
`X-Webhook-Signature` as the SHA-256 HMAC of the raw request body.

## Cache

```php
'cache' => [
    'idempotency_store' => 'array',
    'idempotency_ttl' => 3600,
],
```

## Logging

```php
'logging' => [
    'payload_retention_days' => 90,
],
```
