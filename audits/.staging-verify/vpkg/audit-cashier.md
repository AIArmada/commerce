### Prior-audit section
### cashier (no models by design)
Bugs:
- `StripeGateway:413-423` HIGH — re-encode `json_encode($payload)` breaks Stripe HMAC (needs raw body).
- `SyncWebhook:21-35` HIGH — never calls `verifyWebhookSignature()` (same for `WebhookReplayCommand:33,65`).
- `StripeGateway:147-150` MEDIUM — explicit `'amount'=>null` risks full-refund rejection (omit key).
- `retrievePayment/Invoice:218-261` MEDIUM — cross-owner returns null, indistinguishable from 404.
- `OwnerScopedQuery:17,73-80` LOW — static `hasColumn` cache never cleared; stale after migration under Octane.
Security: Stripe primitive correct but unenforced HIGH — `Webhook::constructEvent` + empty-secret→false GOOD, but callers never verify.
Performance:
- Remote N+1 HIGH — `invoices():297-315` per-invoice `asStripeInvoice()` API fetch.
- `subscriptions():279-289` no pagination LOW.

### Prior-audit fix-first rows
| 6 | cashier | `Gateways/StripeGateway.php:413-423` | `handleWebhook` re-encodes body → Stripe HMAC never verifies | HIGH |
| 7 | cashier | `Actions/SyncWebhook.php:21-35` | No enforced `verifyWebhookSignature()` (same for `WebhookReplayCommand`) | HIGH |
| — | cashier | `Webhook::constructEvent` unenforced | Primitive correct, callers never verify → forged-event processing | HIGH |
