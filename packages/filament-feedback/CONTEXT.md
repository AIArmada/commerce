---
title: Filament Feedback Context
package: filament-feedback
status: active
surface: filament
family: feedback
keywords:
  - filament
  - surveys-ui
  - nps
---

# Filament Feedback Context

## Snapshot
- Composer: `aiarmada/filament-feedback`
- Role: Filament admin for surveys/responses/invitations/templates/testimonials + NPS dashboards.
- Triggers: filament, surveys-ui, nps
- Search first: `src/Resources, src/Pages, src/Widgets, config, docs`
- Related: `feedback`, `commerce-support`
- Paired: `feedback` (core domain owner)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../feedback/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Adapter only: no domain models/actions/calculations. Keep all business rules in `feedback`.
- Filament tenancy is not a security boundary; revalidate every submitted ID server-side (owner scope).
- If behavior or calculations change, move them to `feedback` and keep this package UI-only.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Feedback admin UI.
- Skip when: Scoring/analytics actions — see feedback.
- Owner/security: OwnerUiScope in queries.

## Key surfaces
- Resources: `FeedbackFormResource`, `FeedbackInvitationResource`, `FeedbackResponseResource`, `FeedbackTemplateResource`, `FeedbackTestimonialResource`
- Config `filament-feedback.php`: `navigation`, `group`, `resources`, `enabled`, `feedback_form`, `feedback_response`, `feedback_invitation`, `feedback_template`, `feedback_testimonial`, `navigation_sort`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
