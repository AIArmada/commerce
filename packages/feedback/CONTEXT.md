---
title: Feedback Context
package: feedback
status: active
surface: core
family: feedback
keywords:
  - survey
  - response
  - invitation
  - nps
  - testimonial
  - analytics
---

# Feedback Context

## Snapshot
- Composer: `aiarmada/feedback`
- Role: Surveys, responses, invitations, scoring/analytics, testimonials with lifecycle management.
- Triggers: survey, response, invitation, nps, testimonial, analytics
- Search first: `src/Models, src/Actions, config, docs`
- Related: `commerce-support`, `filament-feedback`, `events`, `engagement`, `contacting`
- Paired: `filament-feedback` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-feedback/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-feedback`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Collecting or analyzing survey feedback.
- Skip when: Social reactions — see engagement.
- Owner/security: Owner-scoped (all models; feedback.owner).

## Key surfaces
- Models: `FeedbackAnswer`, `FeedbackForm`, `FeedbackInvitation`, `FeedbackQuestion`, `FeedbackQuestionOption`, `FeedbackResponse`, `FeedbackSection`, `FeedbackTemplate`, `FeedbackTestimonial`
- Actions/Services: `Actions/ApproveFeedbackTestimonialAction`, `Actions/ArchiveFeedbackFormAction`, `Actions/CalculateFeedbackAnswerScoreAction`, `Actions/CalculateFeedbackFormAnalyticsAction`, `Actions/CalculateFeedbackResponseScoreAction`, `Actions/CloseFeedbackFormAction`, `Actions/CreateFeedbackFormAction`, `Actions/CreateFeedbackFormFromTemplateAction`
- Config `feedback.php`: `database`, `table_prefix`, `json_column_type`, `tables`, `forms`, `sections`, `questions`, `question_options`, `responses`, `answers`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
