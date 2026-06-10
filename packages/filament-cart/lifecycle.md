# Filament Cart — Lifecycle Audit

## 1. Lifecycle States (Display-Only)

The cart snapshot model defines these lifecycle states. The Filament layer consumes them for display, filtering, and actions. No lifecycle logic lives in `filament-cart` — all state transitions are handled by `CartSyncManager` and model methods in the `cart` package.

| State | Trigger | Domain Column(s) | Domain Scope |
|---|---|---|---|
| Active | Items present, recent activity | `items_count > 0`, `last_activity_at` recent | `notEmpty()`, `recent()` |
| Checkout Started | Checkout initiated | `checkout_started_at IS NOT NULL AND checkout_abandoned_at IS NULL` | `inCheckout()` |
| Abandoned | No activity past threshold | `checkout_abandoned_at IS NOT NULL` | `abandoned()` |
| Cleared | Items removed via admin action | `items_count = 0` | (empty) |
| Destroyed | Cart permanently deleted | Row deleted | N/A |
| Merged | Guest→user merge | Identifier updated | N/A |

---

## 2. Table Filter Gaps

### CartsTable (`Resources/CartResource/Tables/CartsTable.php`)

**Existing filters:**
- `instance` (SelectFilter)
- `currency` (SelectFilter)
- `has_items` (Filter — `notEmpty()` scope)
- `has_savings` (Filter — `withSavings()` scope)
- `high_quantity` (Filter — 10+ units)
- `recent` (Filter — 7 days)
- `created_today` (Filter)

**Model scopes NOT wired as table filters:**

| Scope | Description | Filter Type Needed |
|---|---|---|
| `inCheckout()` | `whereNotNull('checkout_started_at')->whereNull('checkout_abandoned_at')` | `SelectFilter` or `Filter` with ternary behavior |
| `abandoned()` | `whereNotNull('checkout_abandoned_at')` | `SelectFilter` or `Filter` |

These scopes exist on the Cart model but are not exposed as user-selectable filters. The `RecentActivityWidget` computes a three-state `status` column (`active`, `checkout`, `abandoned`) via a `CASE` expression but this is not a user-filterable column.

---

## 3. Widget Query Consistency

### 3.1 Status calculation duplication

`CartStatsWidget` and `RecentActivityWidget` both classify carts into lifecycle states but use different query patterns:

| Widget | Active Detection | Checkout Detection |
|---|---|---|
| `CartStatsWidget` | `last_activity_at >= now()->subMinutes(30)` | `checkout_started_at IS NOT NULL AND checkout_abandoned_at IS NULL` |
| `RecentActivityWidget` | `CASE` expression on checkout/abandoned timestamps | Same `CASE` expression |

The "active" definition differs: `CartStatsWidget` uses a 30-minute recency window, while `RecentActivityWidget` derives it from the absence of checkout/abandoned timestamps. These should align.

### 3.2 No lifecycle breakdown widget

There is no widget showing cart distribution by lifecycle state (active/checkout/abandoned/empty). The `RecentActivityWidget` computes these states but displays them as a flat table, not as aggregated stats.

---

## 4. Action Gaps

### 4.1 Missing "Mark as Abandoned" row action

The `MarkAbandonedCartsCommand` can mark all abandoned carts, but there is no individual row action on the table or view page to manually mark a single cart as abandoned.

### 4.2 Clear Cart action — no status filter visibility

The "Clear Cart" row/bulk action and "Delete Cart" action are always visible regardless of cart state. They should be hidden for already-empty or already-destroyed carts.

---

## 5. Owner Scoping

All cart operations (`clear()`, `destroy()`, `markCheckoutStarted()`, `markAsAbandoned()`) run through `OwnerActionGuard::authorizeCart()`. Admin table actions enforce owner scope on writes.

The `CartResource::getEloquentQuery()` applies owner scoping consistently. No gaps identified.

---

## 6. Navigation Consistency

The `CartResource` navigation sort order and grouping are configured via `filament-cart` config. No lifecycle-specific navigation badge is computed (e.g., count of in-checkout or abandoned carts).

---

## 7. Verification Commands

```bash
# 1. PHPStan on filament-cart
./vendor/bin/phpstan analyse packages/filament-cart/src --level=6

# 2. Verify filter coverage matches model scopes
rg -n "scope(InCheckout|scopeAbandoned|scopeNotEmpty|scopeRecent)" packages/cart/src/
rg -n "inCheckout\|abandoned\|notEmpty\|recent" packages/filament-cart/src/Resources/CartResource/Tables/

# 3. Run filament-cart tests
./vendor/bin/pest --parallel packages/filament-cart/tests/

# 4. Pint formatting
./vendor/bin/pint packages/filament-cart/src --test
```
