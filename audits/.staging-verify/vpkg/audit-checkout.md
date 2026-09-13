### Prior-audit section
### checkout
Bugs:
- `CheckoutService:78-91` HIGH unscoped `resumeCheckout` (see queue).
- `CheckoutFinalizer:29-33` MEDIUM (was HIGH "completed_at=null") — bypasses `CheckoutSession::transitionStatus()`; `completed_at=null` disproved (`updating:382-383` still sets it); residual is inconsistent persistence path.
- `CheckoutSession:183,204,236` HIGH lost-update on JSON blobs; nested txn `:436` inside `:107` lengthens locks.
- `CheckoutService:51-71` MEDIUM — no owner assertion on `startCheckout`; cart/customer unvalidated.
- `CreateOrderStep:148-217` MEDIUM — index-aligned pricing (`$pricingItems[$index]`); join on `item_id`.
- `CreateOrderStep:326-329` MEDIUM — `'unknown'` gateway/txn fallbacks collapse `(order,gateway,transaction)` idempotency.
- `PaymentCallbackController:188` residual LOW — "failure/cancel never matches" FALSE (identical gateway keys under success/failure/cancel still pass `array_key_exists`); residual: type-segment not verified.
Security:
- `CheckoutSession:80-116` HIGH mass assignment (see queue).
- `startCheckout:51-71` MEDIUM — session can be ownerless/global.
- Rate-limit-before-verify DoS MEDIUM — `PaymentCallbackController:129-137` `hit()` before `hash_equals`; hit after verify or per-IP+session keys.
- `CheckoutSession:302-308` raw PK update LOW — intentional (commented bypass of Spatie loop); document only. Validator fails closed GOOD.
Performance:
- 20–30 `UPDATE checkout_sessions` per pipeline HIGH — per step `setStepState` + `update(current_step)` + domain updates ×~11 steps. Coalesce or defer to end.
- Per-line pricing loop MEDIUM — `CalculatePricingStep:112-159` per-line `calculate()`; `resolvePriceable:194` unscoped `find` per line then owner-compare (correct reject, still 1 query/line).
- Gateway calls inside txn MEDIUM — `processCheckout:107` txn wraps `stepExecutor->run`; move I/O outside.

### Prior-audit fix-first rows
| 8 | checkout | `Services/CheckoutService.php:78-91` | `resumeCheckout` uses unscoped `find()` — session enumeration | HIGH |
| 9 | checkout | `Models/CheckoutSession.php:80-116` | `owner_*/status/totals/order_id` fillable → free-order / hijack | HIGH |
| — | checkout | `CheckoutSession:183,204,236` | Lost-update on JSON blobs; nested txn lengthens locks | HIGH |
