---
title: Overview
---

# Filament J&T Express

## Purpose

The `aiarmada/filament-jnt` package is the Filament admin adapter for `aiarmada/jnt`.

## What this package owns

- Filament resources for J&T orders, tracking events, and webhook logs
- Filament actions for canceling orders, syncing tracking, and related admin workflows
- J&T-focused dashboard widgets and admin resource screens

## What this package does not own

- J&T API communication, order creation, or webhook processing; those stay in `aiarmada/jnt`
- Generic shipping abstractions; those stay in `aiarmada/shipping`
- Tenant resolution itself; it consumes owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/jnt`](../../jnt/docs/01-overview.md) — core J&T adapter package
- [`aiarmada/shipping`](../../shipping/docs/01-overview.md) — shipping abstraction layer J&T plugs into
- [`aiarmada/filament-shipping`](../../filament-shipping/docs/01-overview.md) — carrier-agnostic shipping admin package

## Main resources actions or surfaces

- **Resources** — `JntOrderResource`, `JntTrackingEventResource`, and `JntWebhookLogResource`
- **Actions** — cancel order and sync tracking
- **Widgets** — `JntStatsWidget`

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/jnt`
- Resource filtering is not authorization; actions and lookups still rely on the core J&T package to enforce owner-safe reads and writes

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core JNT overview](../../jnt/docs/01-overview.md)
