# Filament Orders — Lifecycle Audit

## 1. State Machine Overview

Order lifecycle is governed by `spatie/laravel-model-states` in `packages/orders/src/States/OrderStatus.php`. `filament-orders` reads states for display and calls `OrderService` methods from Filament actions.

---

## 2. Filament Action Mapping

### ViewOrder (`Resources/OrderResource/Pages/ViewOrder.php`)

| Action | Calls `OrderService::` | Visible When |
|---|---|---|
| `confirm_payment` | `confirmPayment()` | `status instanceof PendingPayment` |
| `ship_order` | `ship()` | `status instanceof Processing` |
| `confirm_delivery` | `confirmDelivery()` | `status instanceof Shipped` |
| `complete_order` | `complete()` | `status instanceof Processing \|\| Delivered` |
| `cancel_order` | `cancel()` | `order->canBeCanceled()` |
| `download_invoice` | (route) | `order->isPaid()` |

### Action Gaps

- **No Filament action for**: OnHold, Resume (OnHold→Processing), Fraud, Returned, Refunded — reached only via direct status editing (disabled in form) or external API/job callers
- **No Filament action for**: Return (Shipped/Delivered → Returned) — `Returned` and `Refunded` paths only visible via RefundsRelationManager (read-only table)

---

## 3. Lifecycle Fields Display

| Column | Type | Displayed In | Gap |
|---|---|---|---|
| `status` | JSON (model state) | Table badge, Infolist, Form (disabled) | OK |
| `paid_at` | datetime | Table, Infolist | OK |
| `shipped_at` | datetime | — hidden — | **Gap**: not in any table column, infolist entry, or form field |
| `delivered_at` | datetime | — hidden — | **Gap**: same |
| `canceled_at` | datetime | — hidden — | **Gap**: same |
| `cancellation_reason` | string | — hidden — | **Gap**: not visible |
| `metadata.shipping.carrier` | JSON | Timeline widget | OK |
| `metadata.shipping.tracking_number` | JSON | Timeline widget | OK |

---

## 4. Consistency Issues

### 4.1 Config key mismatch

`FilamentOrdersPlugin.php` reads `config('filament-orders.pages.timeline')` and `config('filament-orders.pages.fulfillment')` but config file `config/filament-orders.php` has no `pages` key. These keys silently fall back to default `true`.

### 4.2 Lifecycle timestamps not displayed

`shipped_at`, `delivered_at`, and `canceled_at` are stored on the model and used by `refreshFormData()` in ViewOrder but are **not exposed** in any Filament table column, infolist entry, or form field. Only `paid_at` and `created_at` appear.

### 4.3 Status distribution widget excludes states

`OrderStatusDistributionWidget.php` hardcodes 9 states (`pending_payment`, `processing`, `on_hold`, `shipped`, `delivered`, `completed`, `canceled`, `returned`, `refunded`) but omits `created`, `fraud`, and `payment_failed`. The widget does not reuse `OrderResource::getStatusOptions()`, creating a maintenance split between the canonical status list and the widget.

### 4.4 OrderForm status field is disabled

`OrderForm.php` renders status as a `Select` with `->disabled()`, making it read-only. No Filament-side action exists to transition to OnHold, Fraud, or Returned — these must be triggered programmatically.

### 4.5 Complete order visibility gate duplicates state-machine rules

`ViewOrder.php` shows "Complete Order" action when `status instanceof Processing || Delivered`. This duplicates transition rules rather than delegating to the model. Prefer using `canBeModified()` / `isFinal()` helpers over raw `instanceof` checks.

### 4.6 FulfillmentPage hardcodes Processing filter

`OrderFulfillmentPage.php` hardcodes `->whereState('status', Processing::class)`. If fulfillment workflow expands (e.g., OnHold items needing fulfillment), this filter must be updated manually.

---

## 5. Status Options Consistency

When adding or removing states, both must be updated:
- `OrderResource::getStatusOptions()` (canonical list)
- `OrderStatusDistributionWidget::getData()` (hardcoded list)

`OrderStatusDistributionWidget` should delegate to `OrderResource::getStatusOptions()` to eliminate duplication.

---

## 6. Verification Commands

```bash
# 1. PHPStan on filament-orders
./vendor/bin/phpstan analyse packages/filament-orders/src --level=6

# 2. Verify widget matches resource status options
rg -n "getStatusOptions" packages/filament-orders/src/
rg -n "pending_payment\|processing\|on_hold" packages/filament-orders/src/Widgets/

# 3. Run filament-orders tests
./vendor/bin/pest --parallel packages/filament-orders/tests/

# 4. Verify no raw instanceof checks in action visibility (prefer model helpers)
rg -n "instanceof" packages/filament-orders/src/Resources/OrderResource/Pages/

# 5. Pint formatting
./vendor/bin/pint packages/filament-orders/src --test
```
