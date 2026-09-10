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
- `PassResource` — View and state transition actions for passes
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
- **Pricing Configuration**: Set prices, currencies, pricing modes
- **Sales Windows**: Configure sale start/end dates
- **Components**: Manage pricing components inline
- **Bundle Products**: Link products to ticket types

### Pass Resource
- **View Passes**: See all issued passes with search and filters
- **State Management**: Activate, use, cancel, revoke, void passes
- **Transfer History**: View pass transfer history
- **Holder Info**: See current and past holder details

### Pass Holder Resource (Read-Only)
- **Holder Lookup**: Search pass holders by name or email
- **Pass History**: See all passes held by a person
- **Relationship View**: Linked customer records (when `aiarmada/customers` is installed)

### Pass Transfer Resource
- **Audit Log**: Complete history of pass transfers
- **Transfer Details**: See old/new holders, reason, authorizer
- **Search**: Filter transfers by pass, holder, or date range

## Owner scoping and security notes

- Resource list queries are owner-safe through shared `commerce-support` helpers
- Submitted IDs are revalidated server-side before any mutation
- State transition actions revalidate the pass's owner before applying
- Bulk actions authorize each record individually
- UI scoping (Filament tenancy) is not authorization — always validate server-side

## Requirements

- PHP 8.4+
- Filament 5.6+
- `aiarmada/ticketing`
- `aiarmada/commerce-support`

## Read next

- [Installation](02-installation.md) — Set up the plugin
- [Configuration](03-configuration.md) — Understand configuration options
- [Usage](04-usage.md) — Learn about resources and common admin flows
- [Troubleshooting](99-troubleshooting.md) — Debug common issues
