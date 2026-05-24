---
title: Overview
---

# Docs Package Overview

## Purpose

The `aiarmada/docs` package owns business-document generation, numbering, PDF output, email delivery, approvals, and e-invoice submission tracking for the Commerce ecosystem.

## What this package owns

- Documents, templates, status history, payments, email records, sequences, versions, approvals, workflows, and e-invoice submissions
- PDF generation through Spatie Laravel PDF and Browsershot-backed templates
- Numbering and sequence management
- Document email delivery, reminder flows, and approval metadata

## What this package does not own

- Order, checkout, or payment domain logic, even when documents reference those models
- Filament admin surfaces; those belong to `aiarmada/filament-docs`
- Owner resolution itself; it consumes `commerce-support` owner context

## Related packages

- [`aiarmada/filament-docs`](../../filament-docs/docs/01-overview.md) — Filament admin resources and reporting surfaces for documents
- [`aiarmada/orders`](../../orders/docs/01-overview.md) and [`aiarmada/checkout`](../../checkout/docs/01-overview.md) — common upstream producers of invoices and related records
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared utilities

## Main models services or surfaces

- **Models** — docs, templates, status history, payments, emails, sequences, versions, approvals, workflows, and e-invoice submissions
- **Services** — document creation, email delivery, and sequence management
- **Outputs** — PDF generation, numbering, reminders, and document lifecycle transitions

## Owner scoping and security notes

- Documents are owner-aware and should follow the `commerce-support` owner-boundary rules
- PDF downloads, email actions, and approval flows should resolve target documents inside the current owner scope before mutating state or exposing files

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [PDF generation](05-pdf-generation.md)
- [Status management](06-status-management.md)
- [Templates](07-templates.md)
- [Tailwind usage](08-tailwind-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Docs overview](../../filament-docs/docs/01-overview.md)