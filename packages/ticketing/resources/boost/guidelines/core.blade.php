# Ticketing

Ticketing owns ticket types, component and product bundles, passes, holders and transfer records. Use IssuePassesAction for issuance, IssuePassesForOrderAction for idempotent order fulfillment, and the dedicated transfer and revocation actions. Maintain owner context for all mutations, especially asynchronous fulfillment. Do not issue additional passes on retries.
