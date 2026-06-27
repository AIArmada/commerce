---
title: Configuration
---

# Configuration

`aiarmada/cashier` publishes `config/cashier.php` as the unified billing layer for gateway selection,
currency defaults, and optional cart integration.

## Configuration file

Publish the config when you need to review or customize the defaults:

```bash
php artisan vendor:publish --tag=cashier-config
```

## Defaults

These keys control the package-wide billing defaults:

| Key | Purpose |
| --- | --- |
| `models.billable` | Billable model class used by the wrapper layer |
| `default` | Default gateway alias, usually `stripe` or `chip` |
| `currency` | Shared fallback currency |
| `locale` | Shared locale for presentation helpers |

## Credentials and gateways

The `gateways` section keeps the gateway aliases and the credentials the wrapper layer needs to
resolve them:

| Key | Purpose |
| --- | --- |
| `gateways.stripe.driver` | Gateway driver name for Stripe |
| `gateways.stripe.secret` | Stripe secret key |
| `gateways.stripe.webhook_secret` | Stripe webhook signing secret |
| `gateways.stripe.currency` | Stripe-specific currency override |
| `gateways.stripe.currency_locale` | Stripe-specific locale override |
| `gateways.chip.driver` | Gateway driver name for CHIP |
| `gateways.chip.brand_id` | CHIP brand identifier |
| `gateways.chip.currency` | CHIP-specific currency override |
| `gateways.chip.currency_locale` | CHIP-specific locale override |

`aiarmada/cashier` does not replace the concrete gateway packages. Stripe still relies on
`laravel/cashier`, and CHIP still relies on `aiarmada/chip` or `aiarmada/cashier-chip` for the
rest of their gateway-specific configuration.

## Payment operations

The `payment_operations.rate_limiting` section protects mutable gateway calls such as Stripe charges,
refunds, and paid subscription creation:

| Key | Purpose |
| --- | --- |
| `payment_operations.rate_limiting.enabled` | Enable rate limiting for mutable payment operations |
| `payment_operations.rate_limiting.max_attempts` | Maximum attempts per gateway, operation, and billable/payment subject |
| `payment_operations.rate_limiting.decay_seconds` | Window length in seconds before attempts are released |

## Integrations

The optional `cart` section controls the tight integration with `aiarmada/cart`:

| Key | Purpose |
| --- | --- |
| `cart.enabled` | Enable or disable the cart integration hooks |
| `cart.register_checkout_macro` | Register a checkout macro on the cart manager |
| `cart.metadata_key` | Metadata key used to persist the cart ID |
| `cart.order_id_key` | Metadata key used to persist the order ID |
| `cart.clear_on_success` | Clear the cart after a successful payment |
| `cart.handle_failure` | Run failure handling logic on payment failures |
| `cart.failure_mode` | Failure strategy: `immediate_release`, `retry_window`, or `hybrid` |
| `cart.retry_window_minutes` | Retry window for `retry_window` and `hybrid` modes |
| `cart.hard_failure_codes` | Gateway error codes that trigger immediate release |
| `cart.allocate_inventory` | Allocate inventory before payment begins |
| `cart.inventory_ttl_minutes` | Reservation TTL for pre-payment allocations |
| `cart.validate_stock` | Re-check stock before checkout starts |

## Example environment values

```env
CASHIER_MODEL=App\Models\User
CASHIER_GATEWAY=stripe
CASHIER_CURRENCY=MYR
CASHIER_LOCALE=en

CASHIER_STRIPE_CURRENCY=USD
CASHIER_STRIPE_CURRENCY_LOCALE=en_US
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

CASHIER_CHIP_CURRENCY=MYR
CASHIER_CHIP_CURRENCY_LOCALE=ms_MY
CHIP_BRAND_ID=your-brand-id
CHIP_COLLECT_API_KEY=your-collect-api-key

CASHIER_CART_ENABLED=true
CASHIER_CART_CHECKOUT_MACRO=true
CASHIER_CART_CLEAR_ON_SUCCESS=true
```

## Related docs

- [Usage](04-usage.md)
- [Subscriptions](05-subscriptions.md)
- [Payments](06-payments.md)
- [Multi-gateway](07-multi-gateway.md)
- [Webhooks](08-webhooks.md)
