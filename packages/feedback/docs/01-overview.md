---
title: Feedback Package Overview
---

# Feedback Package

## What it owns

- Feedback/survey/review/testimonial forms
- Question management with branching and visibility rules
- Response submission with validation and scoring
- Invitation links with secure token hashing
- NPS and CSAT analytics
- Testimonial extraction and moderation
- Domain events for downstream integration

## What it does not own

- Filament admin UI
- Certificate eligibility
- Public marketing pages
- Engagement interactions (likes, shares, comments)

## Model overview

- `FeedbackForm` — survey/feedback form with purpose, status, visibility, and scheduling
- `FeedbackSection` — optional section grouping within a form
- `FeedbackQuestion` — individual question with type, validation, and visibility rules
- `FeedbackQuestionOption` — choices for single/multiple choice questions
- `FeedbackResponse` — one respondent submission
- `FeedbackAnswer` — one answer per question per response
- `FeedbackInvitation` — private invitation link with hashed token
- `FeedbackTemplate` — reusable form blueprint stored as JSON
- `FeedbackTestimonial` — moderated public testimonial extracted from responses

## Tenant scoping

All tenant-owned models use `owner_type` / `owner_id` columns with `HasOwner` and `HasOwnerScopeConfig` from `commerce-support`. Every query and write path enforces owner isolation.

## Integration overview

- `events` — Attach feedback forms to events, occurrences, sessions, speakers, and venues
- `certificates` — Listen to `FeedbackResponseSubmitted` for certificate eligibility
- `engagement` — Consume approved/published testimonials
