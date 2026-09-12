---
title: Troubleshooting
---

# Troubleshooting

## Owner context missing

If queries return empty or throw errors, ensure an owner context is resolved. Use `OwnerContext::withOwner()` for scoped operations.

## Form not visible

Check form `status` and `visibility` settings. Draft forms are not visible to respondents.

## Invitation expired

Invitations have an `expires_at` field set to 14 days by default (configurable via `defaults.invitation_expiry_days`).

## One-response-per-respondent reuse

If `is_one_response_per_respondent` is enabled and the respondent already submitted,
repeated submissions return the existing submitted response.

## Open draft reuse

Starting a response is idempotent while an open draft exists for the same form
and respondent. Submitting that draft continues the existing response instead of
creating a second row.

On PostgreSQL and SQLite, the package also adds a submitted-only unique index for
the one-response backstop. MySQL relies on the action-level checks because it
does not provide portable partial-index syntax.

## Multiple submitted responses

When `is_one_response_per_respondent` is disabled, identified respondents may
submit the same form multiple times. The submitted-only database backstop does
not restrict this mode.

## Answers fail validation

Use `ValidateFeedbackAnswersAction` to debug validation rules. Visibility rules may hide required questions.

## Analytics count mismatch

Analytics queries include owner scoping. Ensure the same owner context is used when comparing counts.

## JSON column type mismatch

Set `FEEDBACK_JSON_COLUMN_TYPE=json` env var or `COMMERCE_JSON_COLUMN_TYPE=json` if your database does not support `jsonb`.

## Filament adapter not showing resources

Ensure the filament-feedback plugin is registered on the panel and the config enables the resources.
