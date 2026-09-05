---
title: Communications Context
package: communications
status: current
surface: domain
family: communications
keywords:
  - email
  - sms
  - notification
  - inbox
  - template
  - suppression
  - delivery
---

# Communications Context

## Snapshot
- Composer: `aiarmada/communications`
- Role: Comms records: outbound/inbound/inbox, deliveries, templates, preferences, suppressions, batches.
- Triggers: email, sms, notification, inbox, template, suppression, delivery
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-communications`, `commerce-support`, `contacting`
- Paired: `filament-communications` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-communications/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-communications`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Sending/recording messages or managing preferences/suppressions.
- Skip when: Contact data itself (emails/phones) — see contacting.
- Owner/security: Owner-scoped (10 models).

## Key surfaces
- Models: `Communication`, `CommunicationAttachment`, `CommunicationAttempt`, `CommunicationBatch`, `CommunicationContent`, `CommunicationDelivery`, `CommunicationDestination`, `CommunicationEvent`, `CommunicationPreference`, `CommunicationRecipient`
- Actions/Services: `Actions/AddCommunicationRecipientAction`, `Actions/ApplyProviderEventAction`, `Actions/AttachCommunicationReferenceAction`, `Actions/CancelCommunicationAction`, `Actions/CancelCommunicationDeliveryAction`, `Actions/CompleteDeliveryAttemptAction`, `Actions/CreateCommunicationAction`, `Actions/CreateCommunicationBatchAction`
- Config `communications.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `batches`, `threads`, `communications`, `recipients`, `contents`, `deliveries`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
