---
title: CHIP Documentation
---

# CHIP Documentation

Laravel integration for CHIP payment platform.

## Architecture

CHIP is designed to be **fully independent** while enabling **seamless integration** with other commerce packages:

- **Standalone** – Works without requiring Cart, Vouchers, or any other package
- **Contract-based** – Uses `CheckoutableInterface` and `CustomerInterface` for loose coupling  
- **Auto-integration** – When Cart is installed, it works immediately with no setup

## Quick Links

| Guide | Description |
|-------|-------------|
| [Payment Gateway](payment-gateway.md) | Universal gateway interface |
| [CHIP Collect](chip-collect.md) | Accept payments |
| [CHIP Send](chip-send.md) | Disbursements and payouts |
| [Webhooks](webhooks.md) | Event handling |
| [API Reference](api-reference.md) | Complete method reference |

## Overview

### CHIP Collect

Accept payments via:
- FPX Online Banking
- Credit/Debit Cards
- E-wallets (Touch 'n Go, GrabPay, Boost)
- DuitNow QR

### CHIP Send

Disburse funds to:
- Malaysian bank accounts
- Vendor payouts
- Affiliate commissions

## Quick Start

```php
use AIArmada\Chip\Gateways\ChipGateway;

$gateway = app(ChipGateway::class);

// Works with any CheckoutableInterface (Cart, Order, Invoice, etc.)
$payment = $gateway->createPayment($checkoutable, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

## Configuration

```env
CHIP_COLLECT_API_KEY=your-api-key
CHIP_COLLECT_BRAND_ID=your-brand-id
CHIP_SEND_API_KEY=your-send-key
CHIP_SEND_API_SECRET=your-send-secret
CHIP_ENVIRONMENT=sandbox
```

## Installation

```bash
composer require aiarmada/chip
php artisan vendor:publish --tag="chip-config"
php artisan vendor:publish --tag="chip-migrations"
php artisan migrate
```

---

**Ready?** Start with [Payment Gateway](payment-gateway.md) →
