# Rewards

Rewards owns program terms, enrollment, entitlements, awards, points and financial ledgers. Use named Actions for every durable mutation. Queue entry points live in `AIArmada\Rewards\EntryPoints\Jobs`; dispatch `EvaluateRewardEntitlementJob` or `RecheckRewardAwardsJob` only for the committed source fact. Source facts are resolved through `RewardSourceFactReader`. Preserve owner scope, idempotency, immutable financial evidence, operation keys, and lock order. Do not create compatibility aliases for moved classes.
