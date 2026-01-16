# AIArmada Filament Affiliates

Filament v5 plugin that turns the `aiarmada/affiliates` package into a full-fledged operator console. Manage partners, inspect attribution streams, review commissions, and drill into linked carts or vouchers without leaving Filament.

## Features

- 🎛️ **Affiliate Resource** – CRUD partners with commission policies, voucher hints, metadata, and owner scoping.
- 🛰️ **Attribution Timeline** – Inspect which carts, campaigns, and UTMs are driving activity.
- 💸 **Conversions & Payouts** – Moderate commission statuses, approve payouts, and export ledgers.
- 🔗 **Deep Links** – When `aiarmada/filament-cart` or `aiarmada/filament-vouchers` exist we render contextual actions that jump to carts or vouchers.
- 📊 **Dashboard Widget** – One look metrics for active affiliates, pending approvals, total commissions, and conversion rates.

## Installation

```bash
composer require aiarmada/filament-affiliates
```

Register the plugin with your Filament panel:

```php
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentAffiliatesPlugin::make(),
        ]);
}
```

Publish configuration to tweak navigation groups, relation managers, and widget defaults:

```bash
php artisan vendor:publish --tag=filament-affiliates-config
```

## Resources

| Resource | Highlights |
| --- | --- |
| **AffiliateResource** | Hero sections for partner context, commission policy editor, relation managers for attributions & conversions, and scoped filters. |
| **AffiliateConversionResource** | Moderation queue with status badges, payout actions, and optional deep links to carts/vouchers. |

## Widgets

- `AffiliateStatsWidget` – KPIs (active affiliates, pending approvals, pending commissions, total paid, conversion rate). Available in dashboards and resource overview tabs.

## Integration Hooks

| Dependency | Benefit |
| --- | --- |
| `aiarmada/filament-cart` | Link conversions back to normalized cart snapshots. |
| `aiarmada/filament-vouchers` | Jump from affiliates or conversions to voucher campaigns that drive traffic. |

All integrations are auto-detected via `class_exists` so you can opt-in without extra configuration.

## License

MIT – see [LICENSE](../../LICENSE).
