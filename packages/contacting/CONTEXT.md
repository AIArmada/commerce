---
title: Contacting Context
package: contacting
status: current
surface: domain
family: foundation
keywords:
  - contact
  - phone
  - email
  - whatsapp
  - social-profile
  - normalization
---

# Contacting Context

## Snapshot
- Composer: `aiarmada/contacting`
- Role: Polymorphic contact methods (email/phone/WhatsApp) + social profiles with normalization and snapshots.
- Triggers: contact, phone, email, whatsapp, social-profile, normalization
- Search first: `src/Models, src/Actions, config, docs`
- Related: `commerce-support`, `addressing`, `filament-contacting`, `communications`
- Paired: `filament-contacting` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-contacting/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-contacting`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Storing contact points or social handles on any model.
- Skip when: Message sending history — see communications.
- Owner/security: Owner-scoped (all 3 models; features.owner config).

## Key surfaces
- Models: `ContactMethod`, `ContactSnapshot`, `SocialProfile`
- Actions/Services: `Actions/BuildContactLinksAction`, `Actions/CreateContactMethodAction`, `Actions/CreateContactSnapshotAction`, `Actions/CreateSocialProfileAction`, `Actions/NormalizeContactMethodAction`, `Actions/NormalizeSocialProfileAction`, `Actions/SetPrimaryContactMethodAction`, `Actions/SetPrimarySocialProfileAction`
- Config `contacting.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `contact_methods`, `social_profiles`, `contact_snapshots`, `defaults`, `country_code`, `public_by_default`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
