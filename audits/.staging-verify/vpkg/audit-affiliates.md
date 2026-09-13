### Prior-audit section
### affiliates
Bugs:
- `CreatePayout:36-100` CRITICAL — read-no-lock `get()` + create payout + conditional claim → overlapping ids paid twice.
- `UpdatePayoutStatus:40-58` HIGH — cancel/fail never refunds `available_minor` (refund lives in `PayoutReconciliationService:95-125`, never called here).
- `MatureConversion:26-48` HIGH — no txn/lock; concurrent matures double-release.
- `ApplyConversionAccounting:90-93` HIGH — unclamped `decrement(holding/lifetime)` → negative.
- `RecordAffiliateConversion:41-171` MEDIUM — no txn; idempotent only with `external_reference`.
- `ApplyConversionAccounting:36-49` MEDIUM — locks Affiliate row, mutates Balance lock-free → TOCTOU.
- `CommissionCalculator:12-22` + `RecordAffiliateConversion:179-181` LOW/HIGH — no rate cap; `payload.commission` trusted.
Security:
- `CreatePayout:65-66` HIGH — `$attributes['owner_*'] ?? conversion->owner_*` with no vs-context validation → re-home payout.
- Unsigned webhook MEDIUM — `WebhookDispatcher:32-33` null signature if secret empty; still queues/sends.
- Attribution cookie bearer MEDIUM — no session/cart fingerprint by default (opt-in `:234-260`).
- API `none` auth LOW — `EnsureApiAuthorized:15-18` disables auth via config; ensure never prod.
- Raw IP/UA PII LOW — persist without hashing/retention note.
Performance:
- `CommissionRuleEngine:$rulesCache` Octane-stale HIGH-verify — `private array` on `singleton(:109)`; verify binding scope (if `scoped`/request, downgrade).
- Report fan-out MEDIUM — per-slice queries; tier/promotion N+1; tenant-aware cache or eager-load.
Good: `ClaimScheduledPayout:62-168` reference impl (locks+FIFO+`operation_key` unique); referral redirect allowlisted.

### Prior-audit fix-first rows
| 2 | affiliates | `Actions/Payouts/CreatePayout.php:36-100` | Double-spend race: read-no-lock → create payout → conditional claim | CRITICAL |
| 13 | affiliates | `Actions/Conversions/MatureConversion.php:26-48` | Lock-free, double-release (approved_at part removed — auto-set) | HIGH |
| 14 | affiliates | `ApplyConversionAccounting::reject:90-93` | Can drive `holding_minor` negative | HIGH |
| 15 | affiliates | `ClaimScheduledPayout` vs `UpdatePayoutStatus:40-58` | Cancelled/failed payouts never refund balance | HIGH |
