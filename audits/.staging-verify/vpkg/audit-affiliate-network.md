### Prior-audit section
### affiliate-network
Bugs:
- `ApplyToOffer:45-96` MEDIUM — check-then-create, no txn/lock/23000 rescue; race → 500 via `unique(offer_id,affiliate_id)`.
- `OfferLinkService:36-56` MEDIUM — arbitrary `target_url` stored; redirect only checks scheme → open redirect.
- Clicks/conversions gameable MEDIUM — raw `increment()`; no bot/dedup/idempotency.
- Counters/sync internals fillable MEDIUM (`OfferLink clicks/conversions/revenue`; `Offer source_checksum/last_synced_at`).
Security: `resolveLink:115-128` acceptable by design — explicit `withOwner(null)`, 64-bit `random_bytes` code, `signed` + `throttle:60,1`. `SiteContentFetcher:27-44` GOOD — `PublicHttpUrlGuard` + pinned client + timeouts + 1MB cap; strategies use `hash_equals`.
Performance: import bounded loop LOW — `OfferImportService:44-58` `array_slice(500)` loop, no chunk/cursor for large syncs.
