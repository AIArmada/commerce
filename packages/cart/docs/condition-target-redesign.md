# Condition Target Redesign Proposal

## 1. Why the Target Needs to Change

The current `CartCondition` `target` field accepts only three string literals (`item`, `subtotal`, `total`). That model was sufficient for the first iteration of the cart, yet it blocks capabilities that modern, internationally deployed carts support out-of-the-box:

- Shipping, duties, payment surcharges, and digital delivery fees cannot be modeled because there is no dedicated stage to anchor the adjustment.
- Mixed carts (physical + digital goods, multiple shipments, subscriptions) need different taxability and promotion rules per group.
- Region-specific rules (e.g., Brazilian IPI + ICMS layering, EU VAT on digital goods, US marketplace facilitator fees) require more than “before total / after total”.
- Large merchants expect “scope selectors” (apply condition to items where `category=apparel`, to shipments handled by `DHL`, to the payment that uses `BNPL`, etc.), cascading phases, and predictable ordering.

Because the target is a plain string with opaque meaning, every new scenario requires branching logic in traits or even bespoke storage flags. Refactoring it from scratch allows us to introduce an explicit targeting model that is powerful yet still approachable.

## 2. Design Goals

1. **Expressive scopes** – Allow a condition to target cart-level values, subsets of items, shipments, payments, fulfillments, or custom aggregates.
2. **Ordered phases** – Give every target a deterministic phase (pre-discount, discountable subtotal, taxable base, post-tax total, etc.).
3. **Selectable anchors** – Support applying to “all items”, “items filtered by attribute(s)”, “each shipment”, “the first payment”, or “a custom aggregator”.
4. **Simple API** – Developers should be able to declare targets via builders/DSL without needing to know the internal math pipeline.
5. **Serializable** – Targets must serialize cleanly to arrays/JSON so storage, queueing, and broadcasting stay straightforward.
6. **Future-proof** – Adding a new scope or phase should not require touching every consumer; value objects and enums isolate the changes.

Backward compatibility is explicitly **not** a requirement; we can break the existing string target to achieve the above.

## 3. Proposed Target Model

### 3.1 Phase Pipeline (Normalized Order of Operations)

All conditions will map to one of the following phases. The order mirrors what large carts (Shopify Plus, BigCommerce Enterprise, Adobe Commerce, Mercado Libre) expose in their adjustment engines while keeping the names generic.

| Phase code      | Description                                                                 | Typical use cases                                               |
|-----------------|-----------------------------------------------------------------------------|------------------------------------------------------------------|
| `pre_item`      | Runs before any per-item price is calculated (unit modifiers, bundle splits)| Volume-based base price overrides, subscription seat pricing     |
| `item_discount` | Applied per item before item subtotal aggregation                           | Coupon per SKU, category-specific discounts, BOGO adjustments    |
| `item_post`     | Runs after per-item subtotal but before cart subtotal                       | Item-level taxes/fees not meant to compound with cart-level ones |
| `cart_subtotal` | Operates on the aggregated subtotal across all relevant items               | Cart promotions, order-level coupons, free shipping thresholds   |
| `shipping`      | Everything related to shipments/fulfillments                                | Carrier fees, shipping discounts, duty prepayments               |
| `taxable`       | Adjusts the base used to compute jurisdictional taxes                       | Taxable base adjustments, taxable gift wrap                      |
| `tax`           | Taxes and tax-like mandates                                                 | VAT, GST, PST, marketplace facilitator taxes                     |
| `payment`       | Applied once a payment method or schedule is selected                       | BNPL fees, COD charges, payment processor incentives             |
| `grand_total`   | Runs last; should be rarely used                                            | Cash rounding, goodwill adjustments, platform commissions        |
| `custom`        | Escape hatch that can anchor to any developer-defined stage                 | Loyalty point redemptions, credit balance applications           |

Internally we can keep this as an enum (`ConditionPhase`) with explicit ordering. Every condition must declare a phase either implicitly (through helpers) or explicitly (through builders/DSL).

### 3.2 Scope + Selector + Application

The phase only controls *when* a condition runs. We also need to capture *what* data the condition is touching and *how* the adjustment gets distributed. Introduce a `ConditionTarget` value object composed of:

```php
final class ConditionTarget implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly ConditionScope $scope,
        public readonly ConditionPhase $phase,
        public readonly ConditionApplication $application,
        public readonly ?ConditionSelector $selector = null,
        public readonly array $meta = [],
    ) {}
}
```

- **Scope (`ConditionScope`)** – Enumerates the domain: `cart`, `items`, `shipments`, `payments`, `fulfillments`, `custom`. Scopes can expose helpers (e.g., `Target::items()`, `Target::shipments()`).
- **Application (`ConditionApplication`)** – Defines distribution: `aggregate` (operate on combined amount and spread proportionally), `per_unit`, `per_item`, `per_group`. This mirrors how Magento and Salesforce Commerce let merchants choose whether a discount applies once or per line.
- **Selector (`ConditionSelector`)** – Encapsulates filters and grouping. A selector can:
  - Filter by metadata (`where('category', 'footwear')`)
  - Filter by structural constraints (`whereQuantity('>', 2)`, `whereAttributeIn('brand', ['Nike', 'Adidas'])`)
  - Group data (`groupBy('shipment_id')`) so the application can act per group.
  - Expose computed metrics (weight, volume, custom attributes) that rules and values can reference.

Selectors remain optional; if omitted the condition targets the entire scope.

### 3.3 Target DSL / Builder

To keep developer experience simple, we expose both fluent builders and a compact DSL string for serialized definitions.

**Fluent example:**

```php
Target::items()
    ->phase(Phase::ITEM_DISCOUNT)
    ->whereAttribute('category', 'electronics')
    ->where('quantity', '>=', 2)
    ->applyPerItem();
```

**DSL example (serializable, human readable):**

```
items:category=electronics;quantity>=2@item_discount/per-item
shipments:carrier=DHL@shipping/per-group
cart@taxable/aggregate
payment:method=bnpl@payment/per-payment
```

The DSL splits into three parts:
1. `scope[:filters]`
2. `@phase`
3. `/application`

Filters are `key op value` statements joined by `;`. We only need to parse a small operator set (`=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not-in`). Complex filters can still be expressed with closures when using the fluent API.

The `CartCondition::fromArray()` factory can accept either a `ConditionTarget` instance, a DSL string, or a structured array:

```php
[
    'target' => [
        'scope' => 'items',
        'phase' => 'item_discount',
        'application' => 'per_item',
        'selector' => [
            ['field' => 'category', 'operator' => '=', 'value' => 'apparel'],
            ['field' => 'attributes.size', 'operator' => 'in', 'value' => ['L', 'XL']],
        ],
    ],
]
```

### 3.4 Example Targets Covering International Scenarios

| Scenario | Target DSL | Notes |
|----------|-----------|-------|
| Free shipping on domestic shipments only | `shipments:destination_country=US@shipping/per-group` | Works even with multiple parallel shipments. |
| Digital VAT applied on downloadable items | `items:type=digital@tax/per-item` | Allows digital goods to receive a different tax rate than physical goods. |
| Duty prepayment on cross-border leg | `shipments:destination_country!=seller_country@taxable/per-group` | Phase `taxable` ensures the duty inflates the taxable base before VAT is calculated. |
| Payment surcharge for COD | `payment:method=cod@payment/per-payment` | SCOPE `payment` lets us treat payment adjustments separately from totals. |
| Marketplace commission on everything sold by third-party sellers | `items:seller_type=marketplace@grand_total/aggregate` | Commission hits last to mimic facilitator marketplaces. |
| Fulfillment fee waived for VIPs | `fulfillments:tier=vip@shipping/per-group` | Introduces `fulfillments` scope distinct from shipments. |

## 4. Engine & API Changes

1. **Introduce Value Objects**
   - `ConditionPhase`, `ConditionScope`, `ConditionApplication` as backed enums.
   - `ConditionSelector` with filter + grouping descriptors.
   - `ConditionTarget` as the composition of the above.

2. **Refactor `CartCondition`**
   - Replace the `string $target` property with `ConditionTarget`.
   - Provide helper constructors: `CartCondition::discount(Target::items()->phase(Phase::ITEM_DISCOUNT), '-10%')`.
   - Update serialization (`toArray`, `jsonSerialize`) to include structured target info and its DSL form for readability.

3. **Rebuild Application Pipeline**
   - Replace `CartConditionCollection::byTarget('subtotal')` calls with `groupByPhase()` and `groupByScope()`.
   - Each phase becomes a method in a new `ConditionPipeline` service that orchestrates the order of operations.
   - Item-level calculations receive a `TargetedItemSet` (items + weights) so selectors can narrow the dataset before applying.

4. **Dynamic Conditions**
   - Rules now receive the resolved selector context (e.g., `$context->items`, `$context->shipments`) to inspect aggregated metrics.
   - Metadata persisted for dynamic conditions stores the DSL representation, keeping rebuild simple.

5. **Storage & Serialization**
   - Database tables gain JSON column(s) for the structured target.
   - Example payload: `{ "scope": "shipments", "phase": "shipping", "application": "per_group", "selector": [ ... ] }`.

6. **Helper APIs**
   - Replace `Cart::addDiscount($name, $value, 'subtotal')` with builders:

    ```php
    Cart::addDiscount(
        name: 'electronics-weekend',
        target: Target::items()->phase(Phase::ITEM_DISCOUNT)
            ->whereAttribute('category', 'electronics')
            ->applyPerItem(),
        value: '-15%'
    );
    ```

   - Provide shortcuts such as `Target::cartSubtotal()`, `Target::shipping($filters = [])`, etc., for common cases so the new model remains approachable.

## 5. Implementation Plan (High-Level)

1. **Foundations**
   - Add enums + value objects.
   - Provide serialization helpers and DSL parser/formatter with extensive unit tests.

2. **CartCondition Refactor**
   - Swap the constructor signature, update factories, adjust event payloads.
   - Update validators to operate on `ConditionTarget`.

3. **Pipeline Rebuild**
   - Introduce a `ConditionPipeline` (or `AdjustmentEngine`) class responsible for walking through phases.
   - Update `CalculatesTotals`, `CartItem` calculations, and `CartConditionCollection` utilities to rely on the pipeline.

4. **Adapters & Helpers**
   - Rewrite `ManagesConditions`, `ManagesDynamicConditions`, `ExampleRulesFactory`, etc., to use the new target API.
   - Update docs, facades, and tests to the new semantics.

5. **Extended Scopes**
   - Add optional data providers for shipments/payments so developers can plug in their own resolvers (useful for headless carts where shipments live elsewhere).
   - Provide default no-op implementations so the core continues to work even if a project only cares about items/subtotals/totals.

6. **Migration Support (Optional)**
   - For projects upgrading within the monorepo, add a command that converts legacy target strings into the closest new DSL form, flagging ambiguous cases.

## 6. Expected Impact

- **Power users** get a targeting system comparable to enterprise carts without hacking around traits.
- **International merchants** can finally encode taxes, shipping, and payment logic that matches legal reality.
- **Framework authors** gain reusable value objects and a documented pipeline, making contributions more predictable.
- **Future features** (multi-currency rounding, loyalty point redemption, etc.) can attach themselves to new phases/scopes without reworking every condition consumer.

## 7. ConditionSelector Details

The selector is the precision instrument of the new targeting architecture. It needs to stay composable, serializable, and performant. We can model it as a tree of predicates plus optional grouping instructions.

```php
final class ConditionSelector implements Arrayable, JsonSerializable
{
    /** @param list<ConditionFilter> $filters */
    public function __construct(
        public readonly array $filters = [],
        public readonly ?ConditionGrouping $grouping = null,
    ) {}
}
```

### 7.1 Filters

`ConditionFilter` is a small immutable struct describing a field path, operator, and value. Field paths can target both first-class properties (`price`, `quantity`, `weight`) and attribute bags (`attributes.category`, `attributes.metadata.fulfillment_type`). Operators are limited and type-aware so we can optimize evaluation without invoking arbitrary closures.

Supported operators (mirrors Adobe Commerce + commercetools filter DSLs):

- Comparison: `=`, `!=`, `>`, `>=`, `<`, `<=`
- Set membership: `in`, `not_in`
- Boolean: `is`, `is_not`
- String ops (optional): `starts_with`, `ends_with`, `contains`

Filters evaluate against the dataset implied by the scope:

- `items` scope exposes `price`, `quantity`, `attributes.*`, `seller`, `tax_class`, etc.
- `shipments` exposes `destination.*`, `carrier`, `weight`, `service_level`.
- `payments` exposes `method`, `installments`, `currency`, `attributes.*`.

Whenever a filter references a field that the scope does not provide, evaluation fails fast with a developer-facing exception so data contracts stay obvious.

### 7.2 Grouping & Weighting

Some adjustments need to operate per logical group (per shipment, per seller, per fulfillment center). `ConditionGrouping` contains:

- `group_by` (field path or callable id)
- `weight_field` (optional; used to proportionally distribute aggregate amounts, e.g., split a cart-level discount by line subtotal)
- `limit` (optional) to cap how many groups are affected (e.g., first N shipments free).

When a grouping is configured:
1. Filter the dataset.
2. Group the rows by `group_by`.
3. Depending on `ConditionApplication`, run the condition per group or on the aggregate of each group.
4. Use `weight_field` (default `subtotal`) to prorate amounts back to lines.

## 8. DSL Grammar

The DSL is intentionally tiny so that support, QA, and solution architects can reason about targets without browsing PHP classes. Proposed EBNF-like grammar:

```
target      ::= scope filters? "@" phase "/" application grouping?
scope       ::= identifier
filters     ::= ":" filter (";" filter)*
filter      ::= field operator value
field       ::= identifier ("." identifier)*
operator    ::= "=" | "!=" | ">" | ">=" | "<" | "<=" | "in" | "not-in" | "~" | "!~"
value       ::= bare_value | "[" csv_list "]"
application ::= "aggregate" | "per-item" | "per-unit" | "per-group" | "per-payment"
grouping    ::= "#" identifier
```

- `value` follows JSON-ish literals (strings, numbers, booleans). When ambiguous, wrap strings in quotes.
- `~` and `!~` (optional) represent contains/does-not-contain for string fields.
- `grouping` (after `/application`) references a named grouping preset (`#shipment`, `#seller`). Presets map to explicit grouping configs stored centrally so DSL strings stay short.

Examples:

- `items:category=in["shirts","pants"];quantity>=2@item_discount/per-item`
- `shipments:destination.country!=origin.country@shipping/per-group#shipment`
- `cart@taxable/aggregate`
- `payment:method=bnpl@payment/per-payment`

When parsed, DSL tokens populate the `ConditionTarget` object. When serialized, we emit both the structured array and DSL string for readability.

## 9. Sample Code Sketches

### 9.1 Constructing a Target

```php
use AIArmada\Cart\Conditions\Target;
use AIArmada\Cart\Conditions\Enums\Phase;
use AIArmada\Cart\Conditions\Enums\Application;

$target = Target::items()
    ->phase(Phase::ITEM_DISCOUNT)
    ->apply(Application::PER_ITEM)
    ->whereAttribute('category', 'electronics')
    ->where('quantity', '>=', 2)
    ->groupBy('seller_id', weightField: 'subtotal');

$condition = CartCondition::discount(
    name: 'electronics-weekend',
    target: $target,
    value: '-15%',
    attributes: ['source' => 'WEEKEND_DROP'],
);
```

### 9.2 DSL Round Trip

```php
$dsl = 'shipments:carrier=DHL;destination.country=US@shipping/per-group#shipment';
$target = ConditionTarget::fromDsl($dsl);
$target->toDsl(); // returns the canonicalized string
$target->toArray(); // useful for storage or broadcasting
```

### 9.3 Pipeline Hook

```php
$pipeline = new ConditionPipeline(
    scopes: [
        new ItemScopeResolver($cart->getItems()),
        new ShipmentScopeResolver($cart->getShipments()),
    ],
    moneyFactory: fn (float $amount) => Money::of($amount, $currency),
);

$result = $pipeline->process($cart->getConditions());
[
    'items' => [...], // adjusted lines
    'shipments' => [...],
    'totals' => [
        'subtotal' => ...,
        'shipping' => ...,
        'tax' => ...,
        'grand_total' => ...,
    ],
];
```

## 10. Upgrade & Collaboration Notes

- **Docs** – Update `conditions.md`, guides, and API references to describe the new phases and DSL. Provide migration cheatsheet translating `subtotal` → `cart@cart_subtotal/aggregate`, `total` → `cart@grand_total/aggregate`, `item` → `items@item_discount/per-item`.
- **Testing** – Build golden master fixtures that describe complex international scenarios (e.g., EU digital VAT, US marketplace facilitator, Brazil dual tax). Ensure regression suites cover proration edge cases (negative totals, rounding).
- **Extensibility** – Document how third parties can register new scopes (e.g., `subscriptions`) by implementing `ScopeResolverInterface` so the pipeline can pull the dataset lazily.
- **Collaboration** – Publish the DSL grammar to frontend/mobile teams so they can preview pricing adjustments without executing PHP.

With the above blueprint, the engineering team can implement a modern targeting layer that keeps AIArmada Cart competitive with enterprise-grade carts while remaining understandable to developers who prefer straightforward APIs.

This proposal intentionally separates *value calculation* from *target declaration*, giving us room to iterate on new operators or inputs later while keeping the targeting language stable and comprehensible.

## 11. Rounding & Multi-Currency Handling

- **Per-phase rounding policy** – Define rounding modes per phase (e.g., `item_discount` rounds per line using tax jurisdiction rules, `grand_total` enforces cash rounding). Provide hooks so markets like Switzerland (5-rappen rounding) or Swedish rounding can plug in.
- **Money factory contract** – Require injected `MoneyFactoryInterface` to create currency-aware money instances, ensuring conversions happen before conditions execute so percentages operate on consistent bases.
- **Currency segregation** – When carts contain mixed currencies (e.g., marketplace with sellers paid in local currency), selectors or scopes must signal currency context so the pipeline either converts or disallows mixing.
- **Negative totals guardrails** – After each phase, enforce `max(0, amount)` (already done today) but surface configurable policies for phases where negative values are legal (e.g., store credit).

## 12. Performance & Resolver Strategies

- **Lazy scope resolvers** – Each scope resolver should implement `Iterable`/`Generator` APIs so large carts stream data instead of materializing entire datasets in memory.
- **Selector caching** – Cache selector results per condition per evaluation cycle; when multiple conditions use identical selectors we can reuse filtered datasets.
- **Batch filters** – Translate simple filters (e.g., attribute equality) into precomputed indexes on the dataset to reduce per-item checks, especially for carts with hundreds of lines.
- **Parallelizable phases** – Phases that only touch independent scopes (e.g., shipments vs. payments) can run concurrently when the host app supports async/promise execution, improving latency.

## 13. Observability & Debugging

- **Condition trace log** – Emit a structured trace (JSON) per evaluation containing the condition, target DSL, selector outcome counts, applied amount, and reasons for skips (rule failed, selector empty, phase disabled).
- **Phase summaries** – Provide helper APIs (or CLI commands) that summarize per-phase deltas so support teams can answer “why did my total change?” without digging through code.
- **Feature flags** – Allow merchants to turn on verbose tracing per cart instance for a limited window, automatically redacting sensitive attributes.

## 14. Governance & Security

- **Target validation** – Validate DSL inputs against whitelist of fields/operators per scope to prevent leaking sensitive data or running expensive filters in hosted admin UIs.
- **Permissioning** – If exposed via dashboard, ensure only authorized roles can create `custom`-phase targets or use selectors touching sensitive attributes (e.g., `customer.loyalty_balance`).
- **Audit logs** – Persist condition definition changes (including DSL string + actor) so compliance teams can trace who introduced a pricing rule.

## 15. Legacy Migration Examples

| Legacy API call | New DSL / Builder equivalent |
|-----------------|------------------------------|
| `target='item'` | `items@item_discount/per-item` |
| `target='subtotal'` | `cart@cart_subtotal/aggregate` |
| `target='total'` | `cart@grand_total/aggregate` |
| “Shipping fee” custom logic | `shipments@shipping/per-group` |
| “Apply discount to physical goods only” | `items:type=physical@item_discount/per-item` |
| “Tax after shipping” | `cart@taxable/aggregate` (configure phase order so `shipping` precedes `taxable`) |

## 16. Next Integration Steps

1. **Trait helpers** – Update `ManagesConditions` convenience methods (`addDiscount`, `addFee`, `addTax`, `addShipping`) so callers can pass a `ConditionTarget` or builder closure, deprecating the raw string target.
2. **Dynamic pipeline** – Introduce a dedicated `ConditionPipeline` orchestrator that walks through `ConditionPhase::cases()` in order, invoking scope resolvers (items, shipments, payments) and applying conditions based on their `ConditionApplication`. This replaces ad hoc `byTarget('subtotal')`/`byTarget('total')` reducer loops and unlocks shipping/tax/payment phases.
3. **Dynamic conditions** – Refactor `ManagesDynamicConditions::evaluateDynamicConditions()` to branch on the new target scope/phase rather than legacy strings, so item-, cart-, shipping-, and payment-level dynamic rules all reuse the same flow.
4. **Event payloads** – Emit `target_definition` in cart events (condition added/removed, cart merged) so downstream systems can inspect the exact scope/phase/application without reconstructing legacy strings.
5. **Tests** – Add PHPUnit coverage for `ConditionTarget::fromDsl()`, selector/grouping parsing, builder round-trips, and the new `CartConditionCollection` helpers before touching the pipeline. These become golden fixtures for future DSL changes.
6. **Migration tooling** – Provide an artisan command that inspects stored conditions, prints the inferred DSL equivalents, and flags ambiguous cases so integrators can migrate confidently.

### 16.1 ConditionPipeline Scaffolding

- A first version of `AIArmada\Cart\Conditions\Pipeline\ConditionPipeline` now exists with supporting context/result objects. It iterates over `ConditionPhase` order, applies any registered phase processors, and defaults to reducing the phase's conditions in sequence.
- Cart instances expose `Cart::evaluateConditionPipeline()` which runs the pipeline against the current cart state and returns structured per-phase data:

```php
$result = Cart::evaluateConditionPipeline();
foreach ($result->phases() as $phase => $phaseResult) {
    printf(
        "[%s] base: %.2f, adjustment: %.2f, final: %.2f\n",
        $phase,
        $phaseResult->baseAmount,
        $phaseResult->adjustment,
        $phaseResult->finalAmount,
    );
}
```

- Custom processors can be registered via `$pipeline->registerPhaseProcessor(ConditionPhase::SHIPPING, $callable)` inside the optional configurator closure passed to `evaluateConditionPipeline()`.
- Future work will hook this pipeline into the core subtotal/total calculations and extend the context so shipment/payment scope resolvers can contribute their own datasets.
- Scope-level resolvers can be registered via `$pipeline->registerScopeResolver(ConditionScope::SHIPMENTS, $resolver)`; a default cart resolver ships out of the box while other scopes fall back to generic reduction until bespoke resolvers are provided.
- Carts can now expose shipment/payment/fulfillment datasets through `resolveShipmentsUsing()`, `resolvePaymentsUsing()`, and `resolveFulfillmentsUsing()`. The built-in resolvers expect iterables of arrays/DTOs containing a `base_amount` and apply conditions according to each target's `ConditionApplication` (aggregate vs per-entry), folding the results back into the running total.

### 16.2 Dynamic Conditions Alignment

- `ManagesDynamicConditions::evaluateDynamicConditions()` now branches on `ConditionScope`, so item targets iterate per line while all other scopes reuse the cart-level flow. Failure handlers receive the target DSL/scope for better diagnostics.
- Persisted metadata stores `target_definition` (plus `target_scope` for convenience), allowing restored conditions to rebuild the precise `ConditionTarget` instead of falling back to a legacy string.
- Cart/item condition events now emit only `target_definition`, keeping downstream consumers aligned with the structured format.
