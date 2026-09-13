### Prior-audit section
### promotions
Bugs:
- `DeactivatePromotion:14` MEDIUM — never sets `deactivated_at`.
- `MarkPromotionAsUsedOnOrderPlaced:106` MEDIUM — atomic `tryIncrementUsage` but no per-order dedup; redelivery double-counts.
- `PromotionService:209-274` MEDIUM — `per_customer_limit` fails open (no customer / missing orders pkg / catch → `true`).
- Global `code` unique LOW — blocks cross-owner reuse + enumeration oracle.
- `Promotion:366` LOW — `round()` vs voucher `intdiv(+5000,10000)` 1¢ drift.
Security:
- `PromotionPerformanceInsights:114-123,203-206` HIGH — owner-blind `Promotion::query()` + unscoped `Order::select()->get()`.
- `DeactivateExpiredPromotionsCommand:23-26` HIGH — cross-tenant sweep; iterate owners explicitly.
- `CreatePromotion/DeactivatePromotion` no `OwnerWriteGuard` MEDIUM — model `saving/updating` enforces only when `promotions.features.owner.enabled` (disabled by default).
Performance:
- `withinCustomerLimit` O(promotions×orders) HIGH — each candidate re-chunks entire order history `:251-263` + PHP JSON parse `:276-298`. Hot cart path.
- Insights full-table loads HIGH — ~8 aggregates + `pluck(all):149` + `Order::get(all):203-206`.
- `DeactivateExpiredPromotionsCommand get()` LOW — `chunkById` + owner iteration.

### Prior-audit fix-first rows
| 28 | promotions | `Support/PromotionPerformanceInsights.php:114-123,203-206` | Owner-blind analytics + unscoped order load | HIGH |
| — | promotions | `DeactivateExpiredPromotionsCommand:23-26` | Cross-tenant sweep; one tenant's cron mutates others' | HIGH |
