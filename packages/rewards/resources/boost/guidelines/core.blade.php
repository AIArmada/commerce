# Rewards

Rewards owns program terms, enrollment, entitlements, awards, points and financial ledgers. Use named Actions for every durable mutation. Queue entry points live in `AIArmada\Rewards\Features\Entitlements\EntryPoints\Jobs`; dispatch `EvaluateRewardEntitlementJob` or `RecheckRewardAwardsJob` only for the committed source fact. Source facts are resolved through `AIArmada\Rewards\Contracts\RewardSourceFactReader`.

For cross-package points entitlement evaluation and reversal, inject `AIArmada\Rewards\Features\Entitlements\Api\RewardPointsEntitlementApi` rather than querying awards or dispatching correction actions directly. Use the owning feature APIs for program locks, enrollment consent, payout snapshots, points operations, and benefit recovery. Feature-owned Eloquent records are not integration contracts. Preserve owner scope, idempotency, immutable financial evidence, operation keys, and lock order. Do not create compatibility aliases for moved classes.
