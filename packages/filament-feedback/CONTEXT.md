---
title: Filament Feedback Package Context
package: aiarmada/filament-feedback
status: active
surface: filament
family: feedback
---

## Snapshot

Composer package: `aiarmada/filament-feedback`.

This package is the Filament v5 admin adapter for `aiarmada/feedback`.

Start code search in:

- `packages/filament-feedback/src/Resources`
- `packages/filament-feedback/src/Pages`
- `packages/filament-feedback/src/Widgets`
- `packages/filament-feedback/src/Support`
- `packages/filament-feedback/config/filament-feedback.php`

Related packages:

- `aiarmada/feedback`
- `aiarmada/commerce-support`
- `aiarmada/events`
- `aiarmada/certificates`
- `aiarmada/engagement`

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../feedback/CONTEXT.md`
- `../commerce-support/CONTEXT.md`

## Guardrails

This package is an adapter only.

Do not create core feedback domain tables here.

Do not implement scoring, submission, invitation token validation, or testimonial state transitions here.

All Filament resources, actions, widgets, exports, and relation managers must be owner-scoped and must call core package Actions for writes.
