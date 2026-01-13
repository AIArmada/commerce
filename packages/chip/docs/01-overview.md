---
title: Overview
---

# CHIP for Laravel

Laravel 12 integration for [CHIP](https://docs.chip-in.asia/) payment platform – **CHIP Collect** (payments) and **CHIP Send** (disbursements).

## What is CHIP?

CHIP is a Malaysian payment gateway that provides:

- **CHIP Collect** - Accept payments via FPX, credit cards, e-wallets, and BNPL
- **CHIP Send** - Send payouts and disbursements to bank accounts

## Package Features

- **Fully independent** – works standalone without requiring other commerce packages
- **Seamless integration** – auto-integrates with Cart when installed together
- **Universal Gateway** – implements `PaymentGatewayInterface` for provider switching
- **Complete API coverage** – purchases, refunds, subscriptions, payouts, webhooks
- **Laravel DX** – facades, fluent builders, typed data objects, events
- **Production ready** – PHP 8.4, PHPStan level 6, comprehensive test suite
- **Secure** – webhook signature verification, sensitive data masking

## Architecture

The package is built with clean architecture principles:

```
aiarmada/chip
├── Actions/         # Single-purpose action classes
├── Builders/        # Fluent API builders (PurchaseBuilder)
├── Clients/         # HTTP API clients (Collect, Send)
├── Commands/        # Artisan commands
├── Data/            # Spatie Data DTOs
├── Enums/           # Type-safe enums
├── Events/          # Laravel events for all webhook types
├── Exceptions/      # Custom exceptions
├── Facades/         # Laravel facades (Chip, ChipSend)
├── Gateways/        # PaymentGatewayInterface implementation
├── Models/          # Eloquent models with owner scoping
├── Services/        # Business logic services
└── Webhooks/        # Webhook handling pipeline
```

## Multi-tenancy Support

Full multi-tenancy support via `commerce-support`:

- Owner-scoped models with `HasOwner` trait
- Auto-assignment on create
- Brand ID to owner mapping for webhooks
- Greppable opt-out via `withoutOwnerScope()`

## Quick Links

- [Installation](02-install.md)
- [Configuration](03-config.md)
- [Usage Guide](04-usage.md)
- [Troubleshooting](99-trouble.md)
