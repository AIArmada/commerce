# Ticketing

Ticketing owns ticket types, component and product bundles, passes, holders and transfer records. Use `IssuePassesAction` for issuance, `IssuePassesForOrderAction` for idempotent order fulfillment, the lifecycle actions for atomic transitions, and the dedicated transfer actions. Transfer notifications and bulk notification jobs carry immutable DTOs; restore owner context before reading holders. Maintain owner context for all mutations, especially asynchronous fulfillment. Do not issue additional passes on retries.
