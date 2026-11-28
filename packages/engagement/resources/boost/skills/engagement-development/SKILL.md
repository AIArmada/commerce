---
name: engagement-development
description: Develop and integrate engagement using its owned Dune features.
---

# Engagement development

Engagement capabilities are owned by `Features/Interactions`, `Features/Reminders`, `Features/Subscriptions`, and `Features/Counters`. Resolve concrete Actions from the container for mutations. Use feature-local model concerns for host integration and the counter read API for aggregate reads. Preserve actor and subject authorization, owner context, reminder idempotency and subscription lifecycle guards.

Run the package architecture checks from the monorepo and the affected Pest regression tests before shipping changes. Do not introduce legacy aliases or duplicate write paths.
