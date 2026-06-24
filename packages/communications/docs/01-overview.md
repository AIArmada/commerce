---
title: Communications Overview
---

# Communications Overview

The `aiarmada/communications` package provides a production-grade communications domain for Laravel applications. It records outbound, inbound, internal, and inbox communication facts, integrates with Laravel Notifications, and provides delivery tracking, template management, preference enforcement, and suppression management.

## Key concepts

- **Communication** — one logical business message (invoice, OTP, marketing email, etc.)
- **Recipient** — snapshot of the intended recipient at creation time
- **Content** — rendered, immutable channel-and-locale snapshot
- **Delivery** — one recipient through one channel
- **Attempt** — one provider invocation or retry for a delivery
- **Event** — append-oriented fact from the application, queue, provider, or webhook
- **Thread** — groups related communications
- **Batch** — business-level grouping for bulk operations
- **Template** — reusable content definition with versioning
- **Preference** — recipient-level channel/category preference
- **Suppression** — hard or temporary prohibition against sending
- **Inbox** — a stored recipient-facing notification row

## Laravel Notifications integration

The package complements Laravel Notifications in two modes:

- **Native observation mode**: listens to `NotificationSending` and `NotificationSent` events and records facts
- **Managed mode**: creates the communication aggregate first, then invokes Laravel Notifications
- **Inbox mode**: creates a communication record and an inbox row for in-app notification surfaces

## Package principles

- Laravel Notifications is the notification API — not replaced
- Laravel Queue is the execution system — not duplicated
- Owner-scoped for multi-tenancy via `commerce-support`
- Inbox rows follow the same owner boundary as the rest of the communications data
- No database foreign-key constraints, cascades, or soft deletes
- All timestamps are timezone-aware
- All primary keys are UUIDs
