### Prior-audit section
### orders
Bugs:
- DONE (2026-09-13, §8 item 10) — `Order:399-409` HIGH — `recalculateTotals` tax-inconsistent (`subtotal=sum(tax-inclusive total)`, keeps `tax_total` separate, `grand=items+shipping-discount` drops tax); disagrees with `CreateOrderFromCart:40-50` by `tax_total`. Fixed: ex-tax subtotal, `grand = subtotal + tax_total + shipping - discount`.
- DONE (2026-09-13, §8 item 10) — `Order:380-384` HIGH — `getBalanceDue = grand-paid+refunded` (10000/10000/2000 → 2000 due, should be 0). Fixed: `grand - paid`.
- `OrderPayment:222-241` MEDIUM — lock-free `exists()` TOCTOU; only `PaymentConfirmed:42-84` handles 23000.
- `CreateOrder:154-176` MEDIUM — strict `(string)===/(int)===` intake compare breaks whitespace-variant retry.
- `OrderItem saving:232-234` LOW — unconditional `total` overwrite, no clamp/quantity check.
Security:
- Mass assignment HIGH — `Order:105-134 owner_*/status/*_at`; `Payment:60-72`, `Refund:63-77`, `Item:68-87 status/*_at`.
- Child inherit when scoping disabled MEDIUM — fall back to unscoped `findOrFail` + inherit when `orders.owner.enabled` off.
- `findExistingIntake:333-340` LOW — `forOwner(includeGlobal)` oracle (conflict vs return reveals totals to guesser).
Performance:
- DONE (2026-09-13, §8 item 10) — 4–5 `sum()` per balance check HIGH — single `SUM(CASE)` or cached columns. Fixed: `paid_total` / `refunded_total` / `pending_refunded_total` folded into the orders create; `OrderPayment`/`OrderRefund` model events are the single sync mechanism (atomic increments), and the five manual mutation sites now refresh instead of assigning.
- Row-by-row inserts + `fresh` in txn MEDIUM — 50 lines = 50+ inserts + selects under lock.

### Prior-audit fix-first rows
| 11 | orders | `Models/Order.php:399-409,380-384` | `recalculateTotals` tax-inconsistent; `getBalanceDue` adds refunds back | HIGH |
| 12 | orders | mass assignment | `owner_*/status/*_at` fillable on Order/Payment/Refund/Item | HIGH |
