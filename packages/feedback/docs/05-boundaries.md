---
title: Feedback Data Boundaries
---

# Feedback Data Boundaries

This package keeps three kinds of customer information distinct.

## What belongs in registration data?

Registration data answers who a person is and what they registered for. Contact
details, profile attributes, event registration, attendance, and other durable
relationship facts belong to the identity, contacting, customers, and events
packages. Feedback may reference a subject or respondent, but it does not copy
or own registration records.

## What belongs in surveys and feedback?

Feedback data answers what a respondent said about a form or experience. Forms,
sections, questions, options, invitations, responses, answers, scores, visibility
rules, and moderated testimonials belong here. Survey answers are response-time
observations and must not be treated as canonical profile or registration data.

## What belongs in social signals?

Social signals answer how people interact with published content. Likes, shares,
comments, reactions, and engagement aggregates belong to the engagement package.
An approved or published testimonial can be consumed by engagement, but feedback
remains the owner of its moderation state and source response.

## How do the packages connect?

Packages connect through owner-safe model references and domain events. Feedback's
traits use explicitly named relations such as `feedbackResponses()` and
`feedbackTestimonials()`. The `responses()` relations in engagement and events
are separate package concerns; this package does not rename those external
relations or depend on them for survey data.
