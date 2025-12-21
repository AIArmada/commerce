# AIArmada Affiliates

Affiliate attribution, referral tracking, and commission workflows for Laravel commerce teams. The package ships with first-class hooks for **aiarmada/cart** and **aiarmada/vouchers**, but remains completely independent – you can store affiliate programs, manage payouts, and expose APIs without installing other commerce packages.

## Highlights

- 🧾 **Programs & Partners** – Model affiliate partners with commission policies, voucher links, metadata, and optional owner scoping for multi-tenant platforms.
- 🎯 **Attribution Graph** – Persist cart-level attribution records (cart identifier + instance) with UTM context, device data, and voucher links.
- 💰 **Commission Engine** – Basis-point and fixed-fee calculations powered by configurable defaults and overridable services.
- 🧩 **Composable Integrations** – Drop-in Cart + Voucher bridges automatically attach affiliates the moment a code or voucher is applied.
- 🍪 **Visit Cookies** – Optional middleware captures affiliate clicks before a cart exists, keeping referrals alive across sessions.
- 📡 **Events Everywhere** – `AffiliateAttributed` and `AffiliateConversionRecorded` events make automation straightforward.

## Installation

```bash
composer require aiarmada/affiliates
php artisan vendor:publish --tag=affiliates-config
php artisan vendor:publish --tag=affiliates-migrations
php artisan migrate
```

### Optional integrations

| Package | Outcome |
| --- | --- |
| `aiarmada/cart` | Adds fluent helpers such as `Cart::attachAffiliate('CODE')`, automatic metadata persistence, and conversion recording utilities. |
| `aiarmada/vouchers` | Reads voucher metadata to auto-attach affiliates, enabling voucher-driven referral programs. |
| `aiarmada/filament-affiliates` | Filament plugin for operating affiliate programs, approvals, and analytics. |

Both integrations are lazy – the service provider detects presence via `class_exists` ensuring you can run affiliates inside bespoke apps or headless APIs.

## Core Concepts

| Model | Purpose |
| --- | --- |
| `Affiliate` | Canonical partner/program definition with status, commission policy, voucher hints, and optional owner scoping. |
| `AffiliateAttribution` | Represents a cart-level session that originated from an affiliate. Stores cart identifier, instance, UTM context, voucher code, agent data, and timestamps. |
| `AffiliateConversion` | A recorded commercial event linked to an affiliate (order, subscription, invoice, etc.) complete with commission totals and payout status. |

### Cart helpers

When `aiarmada/cart` is present the package registers a manager proxy so you can call these fluent methods via the `Cart` facade:

```php
Cart::attachAffiliate('AIARMADA42', [
    'utm_source' => 'newsletter',
    'landing_url' => url()->current(),
]);

Cart::hasAffiliate(); // true

Cart::recordAffiliateConversion([
    'order_reference' => 'SO-100234',
    'subtotal' => $order->subtotal_minor, // optional
    'total' => $order->total_minor,
]);
```

All metadata lives under a configurable key (defaults to `affiliate`) so normalized cart snapshots and third-party systems can read attribution context without any additional joins.

### Voucher bridge

Attach metadata to vouchers and the package will automatically hydrate the cart the moment a voucher is applied:

```php
$voucher->metadata = [
    'affiliate_code' => 'AIARMADA42',
];
```

The `AttachAffiliateFromVoucher` listener handles validation, owner scoping, and persistence so staff can run co-branded campaigns without touching application code.

Set `default_voucher_code` on an affiliate and the listener will fall back to it when no explicit metadata is present (configurable via `affiliates.integrations.vouchers.match_default_voucher_code`).

### Cookie tracking middleware

Activate the `TrackAffiliateCookie` middleware (auto-registered in the `web` group by default) to capture affiliate visits before a cart exists. The middleware:

1. Looks for query parameters such as `aff`, `affiliate`, `ref`, or `referral`.
2. Drops a configurable cookie (defaults to `affiliate_session`).
3. Persists an `AffiliateAttribution` row with UTM metadata, referrer, and device data.
4. Automatically hydrates the cart once the customer starts shopping, ensuring conversions are linked even if the voucher field was never touched.

Tweak behaviour via `config/affiliates.php`:

```php
'cookies' => [
    'enabled' => true,
    'name' => 'affiliate_session',
    'ttl_minutes' => 60 * 24 * 30,
    'query_parameters' => ['aff', 'affiliate', 'ref', 'referral'],
    'require_consent' => false,
    'consent_cookie' => 'affiliate_consent',
    'auto_register_middleware' => true,
    'respect_dnt' => false,
],
```

Disable auto-registration or alias the middleware manually using the `affiliates.cookie` key if you need to scope tracking to custom route groups or consent flows.

Rate limiting and consent

- IP rate limits for attribution capture (`tracking.ip_rate_limit.*`).
- Self-referral blocking when the current owner matches the affiliate owner.
- Consent gate via `cookies.require_consent` with a configurable consent cookie name.

Payout batches

Use `AffiliatePayoutService` to group conversions into payout batches for exports or provider handoff. Conversions are linked via `affiliate_payout_id`, and payout records store aggregate totals and schedule/paid timestamps.
Multi-level uplines are supported via `parent_affiliate_id` on affiliates and `payouts.multi_level.*` config to define commission shares per level.

Webhook + link utilities

- Optional webhooks for attribution/conversion payloads (`events.dispatch_webhooks` + `webhooks.*` endpoints/headers).
- Signed referral links via `AffiliateLinkGenerator` using configurable parameter name, TTL, and signing key.

## Configuration

Key options exposed via `config/affiliates.php`:

- `table_names` – override table names per tenant or schema layout.
- `owner` – plug in a resolver to scope queries per marketplace merchant.
- `cart.metadata_key` – customize the metadata key stored on carts.
- `integrations.vouchers.metadata_keys` – control which metadata paths are inspected for affiliate codes.
- `tracking.block_self_referral` – prevent owners/tenants from crediting their own affiliate code when they are the active owner.
- `cookies.require_consent` / `cookies.consent_cookie` – gate tracking behind explicit consent.
- `commissions` – default currency, rounding, and approval behaviour.

## Events

- `AffiliateAttributed` – fired after a cart is successfully attributed. Includes affiliate + attribution DTOs.
- `AffiliateConversionRecorded` – emitted whenever a conversion row is created. Perfect for payouts or Slack alerts.

Both events are broadcast through Laravel’s dispatcher and can be toggled via config.

## Artisan Commands

The package includes the following commands:

- `php artisan affiliates:export-payout {payout_id}` - Export payout details for a specific payout batch

## License

Released under the [MIT license](../../LICENSE).
