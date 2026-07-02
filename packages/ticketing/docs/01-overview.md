---
title: Overview
---

# Ticketing Package

The Ticketing package provides ticket types, passes, pass issuance, transfers, and bundle products for any ticketable model in the AIArmada Commerce ecosystem.

## Purpose

Use this package when you need to sell tickets for events, workshops, courses, or any other model via a polymorphic `ticketable` relationship.

## What this package owns

- Ticket types with pricing, quotas, and configurable sales windows
- The `ticketable` polymorphic relationship — any model implementing `TicketableInterface` can have tickets
- Pass issuance with unique pass numbers, QR codes, and barcodes
- Pass state machine (Pending → Issued → Activated → Used / Cancelled / Revoked / Voided / Expired)
- Pass transfer with audit log, transfer window expiry, and notifications
- Bulk transfer support
- Bundle products (auto-add required products to cart)
- Cart/checkout integration (optional, via `class_exists()`)
- Inventory stock tracking integration (optional, via `class_exists()`)
- MySQL/PostgreSQL JSON columns (configurable via `json_column_type`)

## What this package does not own

- Filament admin resources, pages, widgets, or panel navigation
- Payment capture, checkout orchestration, or order management
- Inventory stock management or allocation beyond what's needed for ticket quotas
- Customer identity or authentication

## Related packages

- `aiarmada/commerce-support` — owner scoping, money formatting, and shared primitives
- `aiarmada/filament-ticketing` — Filament admin UI for this package
- `aiarmada/inventory` — stock tracking and allocation (optional integration)
- `aiarmada/cart` — add ticket types to shopping cart (optional)
- `aiarmada/products` — ticket-bundled products/variants (optional)
- `aiarmada/orders` — automatic pass issuance on order paid (optional)
- `aiarmada/checkout` — checkout step registration (optional)
- `aiarmada/customers` — pass holder resolution to Customer (optional)

## Main models, services, and surfaces

### Models

- `TicketType` — Ticket types with pricing, quotas, sales windows
- `Pass` — Individual passes with state, unique number, holder info
- `PassHolder` — Person holding a pass (name, email, contact)
- `PassTransfer` — Audit log of pass transfers
- `TicketTypeComponent` — Split ticket pricing components
- `TicketTypeProduct` — Bundle products linked to ticket types

### Actions

- `EnsureTicketTypeAction` — Create or update a ticket type
- `IssuePassesAction` — Issue one or more passes from a ticket type
- `TransferPassToHolderAction` — Transfer a single pass to a new holder
- `BulkTransferPassesAction` — Transfer multiple passes at once
- `ActivatePassAction` — Activate a pass (entry)
- `UsePassAction` — Mark a pass as used
- `CancelPassAction` — Cancel a pass
- `RevokePassAction` — Revoke a pass
- `VoidPassAction` — Void a pass
- `ExpirePassAction` — Expire a pass (usually scheduled)

### Contracts

- `TicketableInterface` — Interface for models that can have tickets
- `PassDeliveryService` — Send pass to holder (email, SMS, etc.)

### Enums

- `PricingMode` — `Flat`, `Tiered`, `Dynamic`, `Free`, `Donation`
- `PassStatus` — `Pending`, `Issued`, `Activated`, `Used`, `Cancelled`, `Revoked`, `Voided`, `Expired`

### States

- `PassState` — Spatie model states for pass lifecycle with allowed transitions

### Listeners

- `IssuePassesOnOrderPaid` — Auto-issue when order is paid (requires `aiarmada/orders`)

## Features

### Ticket Types

- **Pricing**: Flat, tiered, dynamic, free, or donation pricing modes
- **Sales Windows**: Configure when tickets go on and off sale
- **Quotas**: Set max quantity per purchase and total capacity
- **Components**: Split pricing into components (e.g., base price + processing fee)
- **Bundle Products**: Auto-add required products to cart when ticket type is selected

### Pass Lifecycle

- **Issuance**: Generate passes with unique pass numbers, barcodes, and QR codes
- **State Machine**: Track passes through Pending → Issued → Activated → Used / Cancelled / Revoked / Voided / Expired
- **Activation**: Activate passes at entry (scan-based)
- **Usage Tracking**: Mark passes as used with timestamps

### Transfers

- **Single Transfer**: Transfer a pass to a new holder with audit trail
- **Bulk Transfer**: Transfer multiple passes at once (configurable max size)
- **Transfer Window**: Configurable window before event start
- **Expiry Grace**: Additional grace period after transfer window closes
- **Notifications**: Send transfer confirmation emails

### Pass Delivery

- **Pass Number Generation**: Auto-generated unique pass numbers with configurable prefix
- **Delivery Service**: Extensible pass delivery via contract (email, SMS, etc.)

### Owner Scoping

- **Multi-Tenancy**: Passes store the owner tuple and are scoped by `commerce-support`
- **Inherited Scoping**: Ticket types inherit owner boundaries from their ticketable model; holders and transfers inherit from their pass
- **Owner Safety**: Foreign IDs must be revalidated server-side before mutation

## Package Principles

- **Contracts first**: All major capabilities are behind interfaces for testability and override
- **Polymorphic by default**: Any model implementing `TicketableInterface` can sell tickets
- **Optional integration**: Companion packages are detected at runtime via `class_exists()` — zero hard coupling
- **Event-driven**: Pass lifecycle fires events (`PassIssued`, `PassTransferred`) that other packages can listen to
- **Action-based orchestration**: Business logic in reusable Actions, not in controllers or models

## Owner scoping and security notes

- Passes support owner relationships via the `HasOwner` trait
- Ticket types are owner-scoped through the model implementing `TicketableInterface`
- Pass holders and transfers are owner-scoped through their pass
- Inbound foreign IDs such as `ticket_type_id`, `event_id` must be validated as belonging to the current owner scope before mutation
- Use `OwnerWriteGuard::findOrFailForOwner()` or `ResolveOwnedModelOrFailAction` for inbound IDs
- Filament form options are not security — always validate on the server

## Read next

- [Installation](02-installation.md) — Set up the package
- [Configuration](03-configuration.md) — Configure package options
- [Usage](04-usage.md) — Learn how to use the package
- [Troubleshooting](99-troubleshooting.md) — Debug common issues
