---
title: Filament Tax Context
package: filament-tax
status: current
surface: filament
family: catalog-and-identity
keywords:
  - filament
  - tax-ui
  - exemptions
---

# Filament Tax Context

## Snapshot
- Composer: `aiarmada/filament-tax`
- Role: Filament tax config UI: zones/classes/rates/exemptions/certificates/settings.
- Triggers: filament, tax-ui, exemptions
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `tax`, `filament-authz`
- Paired: `tax` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../tax/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `tax`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `tax` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Tax configuration UI.
- Skip when: Tax calculation — see tax.
- Owner/security: OwnerUiScope in queries.

## Key surfaces
- Resources: `TaxClassResource`, `TaxExemptionResource`, `TaxRateResource`, `TaxZoneResource`
- Actions/Services: `Actions/DownloadTaxExemptionCertificateAction`, `Support/FilamentTaxAuthz`
- Config `filament-tax.php`: `features`, `zones`, `classes`, `rates`, `exemptions`, `widgets`, `settings_page`, `certificates`, `disk`, `directory`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-widgets.md`, `06-settings.md`, `07-customization.md`
