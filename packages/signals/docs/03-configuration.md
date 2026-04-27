---
title: Configuration
---

# Configuration

Signals configuration lives in `config/signals.php`.

## Database

Signals uses a table prefix and JSON column type setting. Alert rules/logs and tracked properties use internal owner-scope uniqueness where nullable owners are supported.

## Owner

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

Owner mode is default-on for Signals. Missing owner context fails fast unless explicit global context is used.

## Privacy

```php
'features' => [
    'privacy' => [
        'property_allowlist' => [
            'cart_id',
            'cart_identifier',
            'cart_total_minor',
            'order_id',
        ],
    ],
],
```

Raw PII such as email, phone, names, and full metadata is excluded by default. Add only operational fields that are safe to store.

## Alerts

```php
'features' => [
    'alerts' => [
        'evaluate_on_ingest' => [
            'enabled' => false,
            'queue' => true,
        ],
        'allow_inline_destinations' => false,
        'default_channels' => ['database'],
        'destinations' => [
            'email' => [],
            'webhook' => [],
            'slack' => [],
        ],
    ],
],
```

Scheduled alert evaluation is the baseline. On-ingest evaluation is optional and queued by default.

## Integrations

Integrations are explicit opt-in. Installing Signals does not automatically capture cart data.

```php
'integrations' => [
    'cart' => [
        'enabled' => false,
    ],

    'filament_cart' => [
        'enabled' => false,
        'snapshot_synced_event_name' => 'cart.snapshot.synced',
        'checkout_started_event_name' => 'cart.checkout.started',
        'abandoned_event_name' => 'cart.abandoned',
        'high_value_detected_event_name' => 'cart.high_value.detected',
    ],
],
```

Each enabled integration can auto-create a deterministic tracked property per owner/global context when no single active property exists.
