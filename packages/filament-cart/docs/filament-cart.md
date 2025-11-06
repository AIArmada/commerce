# Filament Cart admin guide

Filament Cart ships a polished admin surface for every normalized cart record—making it effortless for operations, support, and growth teams to investigate carts in real time without touching production storage.

---

## Resource map

| Resource | Route | Primary focus | Highlight features |
| --- | --- | --- | --- |
| **Carts** | `/admin/carts` | Snapshot of each cart instance (default, wishlist, quote, etc.) | Snapshot cards, per-instance filters, bulk housekeeping actions |
| **Cart Items** | `/admin/cart-items` | Every normalized cart line item | Deep filters (price, quantity, conditions), attribute preview, instant search |
| **Cart Conditions** | `/admin/cart-conditions` | Discounts, taxes, fees, shipping and other pricing logic | Type/level filters, applied-to context, real-time rule visibility |

All resources live inside the **E-commerce** navigation group and are auto-registered when you call `FilamentCart::make()` in your Filament panel provider.

---

## Deep dive: carts

- **Purpose**: Provide a high-level view of each cart and how it has evolved over time.
- **Table insights**: cart identifier, instance badge, item count, condition count, total monetary value, timestamps.
- **Filters**: instance (default, wishlist, comparison, quote), activity status (empty vs active), created date range.
- **Bulk actions**: clear carts, re-sync from the source cart instance, export snapshots for analytics.

Because the canonical source of truth remains the aiarmada/cart storage driver, destructive actions are intentionally limited. Most teams rely on this resource for triage and reporting rather than edits.

---

## Deep dive: cart items

- **Purpose**: Normalize every line item into its own row for high-performance search and analytics.
- **Columns**: cart identifier (linked), item name, base price (`Money`-aware), quantity, calculated subtotal, condition count, attribute count, instance, created/updated timestamps.
- **Filters**: range filter for price and quantity, instance selector, “has conditions” toggle, attribute presence, quick search by product name or cart identifier.
- **Show view**: expands item metadata (attributes, selected options) alongside the precise condition snapshot applied at the time of calculation.

The item resource listens to the cart package’s events, so support teams can watch carts evolve live while a shopper continues browsing.

---

## Deep dive: cart conditions

- **Purpose**: Reveal every pricing adjustment (discounts, taxes, shipping, surcharges) in a single, queryable place.
- **Columns**: cart identifier, condition name, type badge, value (percentage or fixed), calculation target (subtotal/total/item), application level (cart vs item), instance, “applies to” context when item-level, timestamps.
- **Filters**: type, level, target, value comparison, instance, quick search by name or cart identifier.
- **Show view**: surfaces rule payloads (minimum spend, brand inclusions, etc.), associated item (if any), and snapshot metadata for audit trails.

Real-time synchronization ensures dynamic conditions registered through `Cart::registerDynamicCondition()` appear immediately and are removed when rules no longer match, giving teams precise visibility into pricing automation.

---

## Access, performance, and safety

- **Read-first design**: Resources are read-only by default to safeguard the canonical cart state. Extend the resources if you want custom administrative actions, but we recommend keeping writes inside your application workflows.
- **Normalized structure**: Indexed columns and dedicated models (`Cart`, `CartItem`, `CartCondition`) deliver 10–100× faster queries than JSON searches.
- **Event-powered sync**: Background listeners translate every cart mutation into normalized records, so the admin UI always reflects the latest state without manual refreshes.

---

## Everyday workflows

| Team | How they use it |
| --- | --- |
| **Support** | Search a shopper’s email/cart identifier, inspect items and discounts, confirm why a condition did or didn’t apply. |
| **Growth** | Monitor promotion uptake by instance, export condition data to BI tools, identify top-performing incentives. |
| **Engineering** | Debug dynamic rules, confirm event synchronization, validate snapshot history after new releases. |

Tips:

- Pin the “Has conditions” filter in the Cart Items resource to spot orders impacted by current campaigns.
- Use the conditions resource’s level filter to separate cart-wide incentives from per-item adjustments when triaging pricing bugs.
- Export selections directly from the table for quick data pulls without hitting production databases.

---

## Extending the experience

You can tailor the resources using standard Filament patterns:

- Override the resource class in your own namespace to add relations, metrics, or custom table actions.
- Register additional navigation groups or badges based on your business logic (e.g., highlight high-value carts).
- Leverage the normalized models in policies, notifications, or scheduled jobs—no need to touch the raw cart session payloads.

Remember to keep Pint (`vendor/bin/pint --dirty`) and Pest (`vendor/bin/pest --parallel`) in your workflow when contributing changes to ensure consistency with the rest of the plugin.

---

The Filament Cart admin suite is the operational cockpit for aiarmada/cart. It’s precise, fast, and safe—giving your teams the confidence to answer any cart question in seconds.