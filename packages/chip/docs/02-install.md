---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12.0+
- `ext-curl` extension

## Install via Composer

```bash
composer require aiarmada/chip
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="chip-config"
```

This creates `config/chip.php` with all configuration options.

## Publish Migrations

```bash
php artisan vendor:publish --tag="chip-migrations"
php artisan migrate
```

The following tables are created:

| Table | Purpose |
|-------|---------|
| `chip_purchases` | Purchase records synced from CHIP API |
| `chip_payments` | Payment transaction details |
| `chip_webhooks` | Webhook event log |
| `chip_bank_accounts` | Bank accounts for CHIP Send |
| `chip_clients` | CHIP client records |
| `chip_send_instructions` | Payout instructions |
| `chip_send_limits` | Send rate limits |
| `chip_recurring_schedules` | App-layer recurring payment schedules |
| `chip_recurring_charges` | Individual recurring charge attempts |
| `chip_daily_metrics` | Pre-aggregated analytics data |

## Environment Variables

Add the following to your `.env` file:

```env
# CHIP Collect (Required)
CHIP_COLLECT_API_KEY=your-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id

# CHIP Send (Optional - for payouts)
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret

# Environment
CHIP_ENVIRONMENT=sandbox

# Webhooks (Required for production)
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
```

## Verify Installation

Run the health check command:

```bash
php artisan chip:health
```

Expected output:
```
CHIP Health Check
=================
✓ API Key configured
✓ Brand ID configured
✓ API connection successful
✓ Webhook signature verification enabled
```

## Next Steps

- [Configure the package](03-config.md)
- [Start using the API](04-usage.md)
