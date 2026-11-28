# Engagement

Engagement capabilities are owned by `Features/Interactions`, `Features/Reminders`, `Features/Subscriptions`, and `Features/Counters`. Resolve concrete Actions from the container for mutations. Use feature-local model concerns for host integration and the counter read API for aggregate reads. Preserve actor and subject authorization, owner context, reminder idempotency and subscription lifecycle guards.

The Events engagement adapter returns detached record arrays for successful writes (including `id` and persisted attributes), or `null` from the null driver. It does not expose Eloquent models across the package boundary.

Collection membership writes lock the collection and bookmark in a consistent order and use exact owner scope, even when global rows are readable. Repeated additions are idempotent; unrelated database errors are not suppressed.
