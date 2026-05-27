---
title: CHIP Context
package: chip
status: current
surface: gateway
family: payments-and-documents
---

# CHIP Context

## Snapshot
- Composer: `aiarmada/chip`
- Role: Direct CHIP Collect and Send gateway integration, webhooks, and payment data.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `src/Jobs`, `config`, `docs`
- Related: `filament-chip`, `cashier-chip`, `checkout`, `docs`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-chip/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns provider-specific API clients, webhooks, and payment data mapping.
- Audit `filament-chip`, `checkout`, `cashier-chip`, and `docs` when request or webhook behavior changes.
- Update `docs/*.md` in the same pass when public behavior or config changes.
