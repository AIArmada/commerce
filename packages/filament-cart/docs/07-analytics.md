---
title: Analytics
---

# Analytics

Filament Cart no longer persists local analytics tables or ships a dedicated analytics page. Cart intelligence is emitted as operational events and can be captured by Signals when explicitly enabled.

## Recommended setup

```bash
composer require aiarmada/signals aiarmada/filament-signals
```

In `config/signals.php`, enable the integrations you need:

```php
'integrations' => [
    'cart' => [
        'enabled' => true,
    ],

    'filament_cart' => [
        'enabled' => true,
    ],
],
```

## Recorded event names

Signals records these Filament Cart events using dotted lower-snake names:

- `cart.snapshot.synced`
- `cart.checkout.started`
- `cart.abandoned`
- `cart.high_value.detected`

Core cart package events can also be captured by enabling `signals.integrations.cart`.

## Where to view reports

Use `filament-signals` pages and resources for:

- dashboards,
- event trends,
- funnels,
- saved reports,
- alert rules,
- alert logs,
- destinations and notification channels.
