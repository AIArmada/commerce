---
title: Configuration
---

# Configuration

## Database

```php
'database' => [
    'table_prefix' => '',
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

## Defaults

```php
'defaults' => [
    'priority' => 'normal',
    'max_attempts' => 3,
],
```

- `defaults.max_attempts` - delivery attempt budget used when a plan or notification does not specify one

## Planning

```php
'planning' => [
    'max_deliveries' => 500,
],
```

- `planning.max_deliveries` - maximum deliveries accepted by one `PlanCommunicationDeliveriesAction` call

## Tracking

```php
'tracking' => [
    'allowed_hosts' => [],
],
```

- `tracking.allowed_hosts` - when non-empty, tracking token target URLs must use one of these hosts; empty allows any `http(s)` host

## Attachments

```php
'attachments' => [
    'allowed_disks' => null,
    'allowed_mimes' => null,
    'max_size_bytes' => 10485760,
],
```

- `attachments.allowed_disks` - when set, `storage_disk` must be one of these disks
- `attachments.allowed_mimes` - when set, `mime_type` must be one of these values (case-insensitive)
- `attachments.max_size_bytes` - `size_bytes` above this limit is rejected on save

Attachment storage fields are validated server-side on every save:
relative `storage_path` values without traversal, well-formed `type/subtype`
mime values, and non-negative sizes within the configured maximum.

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
    'auto_capture_families' => [],
    'auto_capture_triggers' => [],
    'auto_capture_ignored_channels' => [],
],
```

- `owner.enabled` - enable owner scoping for tenant-owned records
- `owner.include_global` - include global rows when scoping queries
- `owner.auto_assign_on_create` - auto-fill the current owner on create
- `native_capture` - observe Laravel Notification events
- `auto_capture` - infer communications from native notifications
- `auto_capture_allowlist` - explicitly allow matching notification classes
- `auto_capture_denylist` - exclude matching classes from auto capture
- `auto_capture_families` - optionally restrict opted-in notifications to `NotificationFamily` values
- `auto_capture_triggers` - optionally restrict opted-in notifications to `NotificationTrigger` values
- `auto_capture_ignored_channels` - skip selected notification channels

The standalone package binds consent, preference, quiet-hours, rate-limit, and
suppression contracts to `PermissiveEligibilityResolver`. Custom bindings must
implement the renamed `resolveConsent()` and `resolveSuppression()` methods;
the old method names are not retained.

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
    'middleware' => ['api', 'throttle:communications-webhooks', VerifyWebhookSignature::class],
    'providers' => [
        'provider-name' => [
            'enabled' => true,
            'secret' => env('PROVIDER_WEBHOOK_SECRET'),
            'algorithm' => 'sha256',
            'signature_header' => 'X-Webhook-Signature',
        ],
    ],
    'max_payload_bytes' => 262144,
    'max_payload_depth' => 32,
    'timestamp_header' => 'X-Webhook-Timestamp',
    'timestamp_tolerance_seconds' => 300,
    'rate_limit' => [
        'max_attempts' => 60,
    ],
    'route_name_prefix' => 'communications.webhooks.',
],
```

The default middleware fails closed unless the provider is configured and sends
the configured signature header as the HMAC of the raw request body plus a
recent numeric `X-Webhook-Timestamp`. Replayed timestamps outside the
configured tolerance are rejected before dispatch. Each provider entry accepts
an optional `algorithm` (any `hash_hmac_algos()` value, default `sha256`) and
an optional `signature_header` (default `X-Webhook-Signature`).

Payloads larger than `max_payload_bytes` or nested deeper than
`max_payload_depth` are rejected with `413` before a job is queued.

The default `WebhookOwnerResolver` binding returns `null`. Provider events
applied without a resolved owner derive the delivery's owner scope from the
delivery itself; bind a custom resolver when webhooks must resolve ownership
from provider-specific payload fields.

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
