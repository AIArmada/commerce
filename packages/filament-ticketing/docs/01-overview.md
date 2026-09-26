---
title: Overview
---

# Filament Ticketing Plugin

The Filament Ticketing package provides the Filament admin UI for the `aiarmada/ticketing` domain package.

## Purpose

Use this package when you need panel resources for ticket type management, pass administration, pass holder lookup, and transfer audit logs inside Filament.

## What this package owns

- `FilamentTicketingPlugin` — Panel plugin registration
- `TicketTypeResource` — CRUD for ticket types
- `PassResource` — Read-only pass viewing
- `PassHolderResource` — Read-only pass holder lookup
- `PassTransferResource` — Transfer audit log
- Owner-safe query wiring for all resources

## What this package does not own

- Ticketing-domain models, actions, state machines, or persistence rules
- Pass issuance, transfer, or lifecycle orchestration
- Owner resolution itself beyond consuming shared `commerce-support` behavior
- Checkout, cart, or order management

## Related packages

- `aiarmada/ticketing` is the source of truth for the ticketing domain model and actions
- `aiarmada/commerce-support` provides shared owner-scoping utilities used by the resources
- Other `filament-*` packages may surface related data, but ticket administration lives here

## Main resources or surfaces

- `FilamentTicketingPlugin`
- `TicketTypeResource`
- `PassResource`
- `PassHolderResource`
- `PassTransferResource`

## Features

### Ticket Type Resource
- **Full CRUD**: Create, edit, and manage ticket types
- **Pricing Configuration**: Set price, currency, access type, and seating mode
- **Quantities**: `admits_quantity`, `min_quantity`, `max_quantity`
- **Sales Windows**: Configure sale start/end dates
- **Components**: Attach component ticket types with a quantity, in the Components relation manager
- **Bundle Products**: Link products to ticket types in the Products relation manager

### Pass Resource
- **View Passes**: Search all issued passes and filter by status
- **Pass Details**: Pass no, ticket type, current holder, QR code, barcode, status, and every lifecycle timestamp
- **Read Only**: the resource registers no state transition, transfer, or delete actions — lifecycle changes go through the core `aiarmada/ticketing` services

### Pass Holder Resource (List Only)
- **Holder Lookup**: Search pass holders by name, email, or pass number
- **Pass Link**: Each row shows the holder's pass number and whether the record is the current holder

### Pass Transfer Resource (List Only)
- **Audit Log**: Every pass transfer in the current owner scope
- **Transfer Details**: Pass number, from holder, to holder, reason, and timestamp

## Owner scoping and security notes

- Resource list queries are owner-safe through shared `commerce-support` helpers
- Submitted IDs are revalidated server-side before any mutation
- State transition actions revalidate the pass's owner before applying
- Bulk actions authorize each record individually
- UI scoping (Filament tenancy) is not authorization — always validate server-side

## Requirements

- PHP 8.4+
- Filament 5.8+
- `aiarmada/ticketing`
- `aiarmada/commerce-support`

## Read next

- [Installation](02-installation.md) — Set up the plugin
- [Configuration](03-configuration.md) — Understand configuration options
- [Usage](04-usage.md) — Learn about resources and common admin flows
- [Troubleshooting](99-troubleshooting.md) — Debug common issues
