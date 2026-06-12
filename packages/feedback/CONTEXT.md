---
title: Feedback Package Context
package: aiarmada/feedback
status: active
surface: core
family: feedback
---

## Snapshot

Composer package: `aiarmada/feedback`.

This package owns the core feedback, survey, response, invitation, scoring, analytics, and testimonial domain.

Start code search in:

- `packages/feedback/src/Models`
- `packages/feedback/src/Actions`
- `packages/feedback/src/Analytics`
- `packages/feedback/src/Events`
- `packages/feedback/database/migrations`
- `packages/feedback/config/feedback.php`

Related packages:

- `aiarmada/commerce-support`
- `aiarmada/filament-feedback`
- `aiarmada/events`
- `aiarmada/certificates`
- `aiarmada/engagement`
- `aiarmada/contacting`

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../commerce-support/CONTEXT.md`
- `../filament-feedback/CONTEXT.md`

## Guardrails

This package owns the feedback domain only.

Do not put Filament resources, pages, widgets, or admin UI here.

Do not put certificate eligibility, event attendance, or engagement interaction logic here.

Feedback forms and responses are tenant-owned through `owner_type` / `owner_id` and must enforce owner scoping on every read and write path.

Use UUID primary keys, configurable table names, configurable JSON column type, no database foreign constraints, no database cascades, and no soft deletes.
