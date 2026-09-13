### Prior-audit section
### jnt
Bugs:
- `ProcessJntWebhook:493-500` HIGH — `CarbonImmutable::parse()` throws on bad `scanTime` in `usort` comparator + sync paths → retry loop.
- `JntTrackingEvent:80-106`, `Parcel:49-70`, `Item:50-74` HIGH — inherit-without-authorize (unscoped parent copy, no `OwnerContext::resolve()` check; contrast `cart/Condition:536-553`).
- Per-detail inserts MEDIUM — per-row `create()` + catch-unique-skip; use `upsert(event_hash)` (unique exists).
- Last-write-wins MEDIUM — `fill($updates)->save():461-462` no txn/lock.
- `JntWebhookLog:125-128` MEDIUM — hardcoded `'webhook_calls'`, ignores prefix/tables.
- `JntOrder:224-231` LOW — non-atomic cascade, no txn/chunk.
Security:
- `JntSpatieSignatureValidator:17-22` CRITICAL (narrowed) — `verify_signature=false` bypass with NO prod fail-closed (contrast checkout). Default `true` + empty-secret fails closed, so requires explicit opt-out.
- `JntTrackingEvent/Parcel/Item creating` HIGH (above).
- `WebhookController:75-103` MEDIUM — distinct 401 vs 422 oracle; use uniform `200+{code:0}` per J&T spec.
- `verifyAndParse:155-165` LOW — signs parsed `input('bizContent')` not raw `getContent()`; false negatives.
- `AwbController:17-53` GOOD reference — `hasValidSignature` + `OwnerSignedDownload` + `no-store`.
Performance:
- O(n log n) re-sort per webhook MEDIUM — J&T already chronological; single max-scan O(n).
- `latest('scan_time')` per order N+1 LOW — `latestOfMany`/subquery.

---

### Prior-audit fix-first rows
| 5 | jnt | `Webhooks/JntSpatieSignatureValidator.php:17-22` | `verify_signature=false` accepted with no prod fail-closed (checkout refuses) | CRITICAL |
| — | jnt | `ProcessJntWebhook:493-500` | `CarbonImmutable::parse()` throws on bad `scanTime` → retry loop | HIGH |
| — | jnt | `JntTrackingEvent/Parcel/Item creating` | Inherit-without-authorize, no context check | HIGH |
