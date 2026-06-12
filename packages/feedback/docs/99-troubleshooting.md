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

## One-response-per-respondent blocking

If `is_one_response_per_respondent` is enabled and the respondent already submitted, new submissions are rejected.

## Answers fail validation

Use `ValidateFeedbackAnswersAction` to debug validation rules. Visibility rules may hide required questions.

## Analytics count mismatch

Analytics queries include owner scoping. Ensure the same owner context is used when comparing counts.

## JSON column type mismatch

Set `database.json_column_type` to `json` if your database does not support `jsonb`.

## Filament adapter not showing resources

Ensure the filament-feedback plugin is registered on the panel and the config enables the resources.
