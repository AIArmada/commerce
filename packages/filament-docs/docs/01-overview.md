---
title: Overview
---

# Filament Docs Overview

## Purpose

The `aiarmada/filament-docs` package is the Filament admin adapter for `aiarmada/docs`.

## What this package owns

- Filament resources for documents, templates, sequences, and email templates
- Aging report and pending-approvals pages
- Document-focused dashboard widgets, exports, and secure download surfaces

## What this package does not own

- Document persistence, PDF generation, numbering rules, or email delivery; those stay in `aiarmada/docs`
- Owner resolution itself; it consumes the owner context from the host app and `commerce-support`

## Related packages

- [`aiarmada/docs`](../../docs/docs/01-overview.md) — core document generation and lifecycle package
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared infrastructure

## Main models services or surfaces

- **Resources** — docs, templates, sequences, and email templates
- **Pages** — aging report and pending approvals
- **Widgets** — doc stats, quick actions, recent documents, revenue chart, and status breakdown
- **Actions** — payment recording and email sending

## Owner scoping and security notes

- The plugin should mirror the owner-scoping behavior defined by `aiarmada/docs`
- Resource filtering is not authorization; secure downloads and admin actions still rely on the core docs package to enforce owner-safe reads and writes

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Pages and widgets](05-pages-widgets.md)
- [Customization](06-customization.md)
- [Troubleshooting](99-troubleshooting.md)
- [Core Docs overview](../../docs/docs/01-overview.md)