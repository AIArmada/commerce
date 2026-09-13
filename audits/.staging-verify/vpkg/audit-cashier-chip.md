### Prior-audit section
### cashier-chip
Bugs:
- DONE (2026-09-13, §8 item 4) — No `(subscription_id,period_key)` unique MEDIUM-HIGH — `ClaimRenewalAttempt:21-69` SELECT-then-INSERT serialized only by subscription lock; crashed lease → double-bill. Fixed: kept additive (partial unique where not null) with atomic claim + 23000 rescue.
- `WebhookCommand:55` MEDIUM — dead key `cashier-chip.webhooks.verify_signature` display-only; real switch in `chip`.
Security:
- `PaymentMethodStore:72-182` GOOD — scoped → `withoutOwnerScope` re-check → `AuthorizationException` on cross-tenant.
- `validate_billable_owner=false` kill-switch LOW.
- DONE (2026-09-13, §8 item 12) — `RenewalAttempt` no owner columns MEDIUM — isolation via `belongsTo subscription` join only. Fixed: kept additive (owner columns + chunked backfill from parent subscriptions); `HasOwner`/`HasOwnerScopeConfig` with inheritance and `OwnerWriteGuard` on claim paths.
Performance: `ChipSubscription items` N+1 MEDIUM (corrected citation `:1158` with `loadMissing:1140`) — eager-load `items` at query site.

### Migration-batch rows (§8, code may already be fixed)
| 4 | cashier-chip `(subscription_id, period_key)` unique (§5) | Kept additive (`2026_09_13_000001_*`); atomic claim + 23000 rescue | `packages/cashier-chip/docs/09-subscriptions.md` |
| 12 | `RenewalAttempt` owner columns (§5) | Kept additive (columns + chunked backfill); `HasOwner` + inheritance + guards | `packages/cashier-chip/docs/01-overview.md`, `02-installation.md`, `09-subscriptions.md`, `11-testing.md` |
