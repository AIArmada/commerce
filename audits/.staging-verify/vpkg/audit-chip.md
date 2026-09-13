### Prior-audit section
### chip
Bugs:
- Float on minor units HIGH — `PurchaseDetailsData:96`, `ProductCollection:57,69`, `ProductData:105 (int)(amount*(float)qty)`; use half-up like `ChipIntegerModel:145`.
- `PurchasePaidHandler:27-29` MEDIUM — only `status=PAID`; never updates `paid_on/total_minor/payment_method`.
- `SyncPurchaseRefundState:56-63` MEDIUM — partial also `status=refunded` + `refunded_at` (no `partially_refunded` state).
- Refund sum-then-save race MEDIUM — no txn/lock.
- Duplicate in-flight webhooks both dispatch MEDIUM — `isDuplicate:97-110` only skips `processed`.
- `ChipCustomerDirectory:31-46` MEDIUM — `owner=null` adds no scope; callers must pass owner.
Security: verified safe — `verify_signature=false` throws in prod; secrets env + redaction; no server-side SSRF. `WebhookSimulator:154-156` disables verify test-only, acceptable.
Performance: indexes GOOD — `idempotency_key` unique, GIN metadata. No polling. Retry `usleep` is GET/HEAD/OPTIONS-only, never on mutations.

### Prior-audit fix-first rows
| 32 | chip | `Data/PurchaseDetailsData.php:96`, `ProductCollection.php:57,69`, `ProductData:105` | Float math on minor units, truncation | HIGH |
