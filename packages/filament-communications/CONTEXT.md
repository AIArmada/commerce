---
title: Filament Communications Context
package: filament-communications
status: current
surface: filament
family: communications
keywords:
  - filament
  - comms-inbox
  - deliveries-ui
---

# Filament Communications Context

## Snapshot
- Composer: `aiarmada/filament-communications`
- Role: Read-focused ops UI for messages, deliveries, threads, templates, preferences, suppressions, batches.
- Triggers: filament, comms-inbox, deliveries-ui
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `communications`, `commerce-support`
- Paired: `communications` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../communications/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `communications`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `communications` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Comms operations UI.
- Skip when: Send/record logic — see communications.
- Owner/security: OwnerUiScope in resources + widget.

## Key surfaces
- Resources: `CommunicationBatchResource`, `CommunicationDeliveryResource`, `CommunicationPreferenceResource`, `CommunicationResource`, `CommunicationSuppressionResource`, `CommunicationTemplateResource`, `CommunicationThreadResource`
- Config `filament-communications.php`: `navigation`, `group`, `sort`, `resources`, `communications`, `enabled`, `deliveries`, `enabled`, `threads`, `enabled`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
