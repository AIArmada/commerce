---
title: Filament Feedback Overview
---

# Filament Feedback Package

This is the Filament v5 admin adapter for `aiarmada/feedback`. It provides resources, pages, widgets, and exports for managing feedback forms, responses, invitations, templates, and testimonials.

## Adapter boundary

This package owns only the admin experience. All domain logic, persistence, scoring, and events live in the core `aiarmada/feedback` package.

## Available resources

- FeedbackFormResource — create and manage forms
- FeedbackResponseResource — view and moderate responses
- FeedbackInvitationResource — manage invitation links
- FeedbackTemplateResource — manage reusable templates
- FeedbackTestimonialResource — moderate testimonials

## Owner scoping

All resources apply `OwnerUiScope` to enforce tenant isolation. Submitted IDs are validated server-side.
