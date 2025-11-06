# CHIP for Laravel

> Seamless Laravel 12 integration for the [CHIP](https://docs.chip-in.asia/) payment platform – covering both **CHIP Collect** (payments) and **CHIP Send** (disbursements).

[![Packagist](https://img.shields.io/packagist/v/aiarmada/chip.svg?style=flat-square)](https://packagist.org/packages/aiarmada/chip)
[![Tests](https://img.shields.io/github/actions/workflow/status/aiarmada/chip/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/aiarmada/chip/actions?query=workflow%3Atests)
[![Pint](https://img.shields.io/github/actions/workflow/status/aiarmada/chip/pint.yml?branch=main&label=pint&style=flat-square)](https://github.com/aiarmada/chip/actions?query=workflow%3Apint)

## Why this package?

- **Complete API coverage** – purchases, refunds, subscriptions, payouts, webhooks, statements.  
- **First-class Laravel DX** – facades, fluent builders, data objects, events, queues, health checks.  
- **Production ready** – PHP 8.4 / Laravel 12, PHPStan level 8, Pest v4 test suite.  
- **Secure by default** – webhook signature verification, optional masking of request/response logs.

📚 **Full API reference:** [`docs/CHIP_API_REFERENCE.md`](docs/CHIP_API_REFERENCE.md)

---

## Installation

```bash
composer require aiarmada/chip
```

Publish configuration and (optionally) package migrations:

```bash
php artisan vendor:publish --tag="chip-config"
php artisan vendor:publish --tag="chip-migrations" # optional persistence
php artisan migrate
```

### Database JSON type (PostgreSQL)

Migrations default to portable `json` columns. To opt into `jsonb` on a fresh install, set one of the following BEFORE running migrations:

```env
COMMERCE_JSON_COLUMN_TYPE=jsonb
# or per-package override
CHIP_JSON_COLUMN_TYPE=jsonb
```

Or run the interactive setup:

```bash
php artisan commerce:configure-database
```

When using PostgreSQL + `jsonb`, a GIN index is created automatically on `purchases.metadata`. Other JSON fields are left unindexed by default; add indexes based on your query patterns.

### Environment variables

```env
# CHIP Collect
CHIP_COLLECT_API_KEY=your-collect-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id
CHIP_COLLECT_BASE_URL=https://gate.chip-in.asia/api/v1/
CHIP_COLLECT_ENVIRONMENT=sandbox # or production

# CHIP Send
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret
CHIP_SEND_ENVIRONMENT=sandbox # or production

# Webhooks & logging
CHIP_WEBHOOK_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
CHIP_LOGGING_ENABLED=false
```

All options live in `config/chip.php`, including timeout/retry settings, default currency, webhook middleware and cache TTLs.

---

## Usage

### CHIP Collect (payments)

```php
use AIArmada\Chip\Facades\Chip;

$purchase = Chip::createPurchase([
    'client' => [
        'email' => 'customer@example.com',
        'full_name' => 'Jane Customer',
    ],
    'purchase' => [
        'currency' => 'MYR',
        'products' => [
            ['name' => 'Pro Plan', 'price' => 12900],
        ],
        'success_redirect' => route('payments.success'),
        'failure_redirect' => route('payments.failed'),
    ],
]);

return redirect($purchase->checkout_url);
```

Need the fluent builder?  
`Chip::purchase()->brand('brand-id')->customer('user@example.com')->addProduct('Add-on', 500)->create();`

### CHIP Send (payouts)

```php
use AIArmada\Chip\Facades\ChipSend;

$instruction = ChipSend::createSendInstruction(
    amountInCents: 10500,
    currency: 'MYR',
    recipientBankAccountId: 'bank_acc_123',
    description: 'Affiliate Commission',
    reference: 'AFF-2025-0001',
    email: 'affiliate@example.com',
);
```

### Webhook verification

```php
use AIArmada\Chip\Http\Requests\WebhookRequest;

Route::post('/chip/webhook', function (WebhookRequest $request) {
    $payload = $request->getWebhookPayload();

    if ($payload->event === 'purchase.paid') {
        // handle success
    }

    return response('OK');
})->middleware(['verify-chip-signature']);
```

The package automatically verifies signatures when `CHIP_WEBHOOK_VERIFY_SIGNATURE=true`. Configure allowed events and middleware stack inside `config/chip.php`.

### Health check command

```bash
php artisan chip:health
```

Outputs Collect/Send connectivity status, configuration summary (with `-v`), and exits with `1` if any check fails – ideal for CI or uptime probes.

---

## Documentation & Examples

- [`docs/CHIP_API_REFERENCE.md`](docs/CHIP_API_REFERENCE.md) – curated endpoint reference.  
- [`tests/`](tests) – Pest-powered examples of purchases, payouts, webhooks, CLI commands.  
- [`src/Builders/PurchaseBuilder.php`](src/Builders/PurchaseBuilder.php) – fluent API for advanced purchase creation.  
- [`src/Services`](src/Services) – typed service layer the facades delegate to.

---

## Contributing

1. Fork & clone the repository.  
2. Install dependencies: `composer install`.  
3. Run the test suite: `vendor/bin/pest`.  
4. Apply formatting: `vendor/bin/pint`.  
5. Submit a pull request with a clear description and, ideally, a link to the relevant CHIP documentation.

Bug reports and feature requests are welcome via GitHub issues. Please include reproduction steps and API responses (redacted for sensitive data).

---

## License

Released under the MIT License. See [LICENSE](LICENSE.md) for details.
