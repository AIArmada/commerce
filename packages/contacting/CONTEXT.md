---
title: Contacting Context
package: aiarmada/contacting
status: current
surface: domain
family: foundation
---

# Contacting Context

## Snapshot

- Composer: `aiarmada/contacting`
- Role: Contact methods (email, phone, WhatsApp, etc.) and social profiles (Facebook, Instagram, TikTok, etc.) for any entity.
- Search first: `src/Actions`, `src/Models`, `src/Data`, `src/Enums`, `src/Support`, `src/Concerns`, `config`, `docs`
- Related: `commerce-support`, `addressing`, `engagement`

## Read next

1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails

- Owns contact methods, social profiles, contact snapshots, polymorphic relationships, normalization, and formatting.
- Does NOT own physical addresses, notification delivery, sharing actions, or CRM pipelines.
- Addresses belong to `aiarmada/addressing`. Share actions belong to `aiarmada/engagement` or `aiarmada/sharing`.
- Keep Filament resources, pages, widgets, and relation managers in a separate `filament-contacting` adapter package.
- Preserve owner-aware queries when owner scoping is enabled.
- Update `docs/*.md` in the same pass when public behavior or config changes.