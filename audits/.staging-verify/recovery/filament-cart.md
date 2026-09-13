End-to-end review of packages/filament-cart (adapter-only Filament v5 UI over cart snapshots/conditions; no models/migrations/routes in package).

FINDINGS

1) severity: high | category: bug | packages/filament-cart/src/Resources/CartResource/RelationManagers/ConditionsRelationManager.php:19
Title: Relation manager renders snapshot conditions with the stored-Condition table config
Description: Relationship `cartConditions` returns `CartSnapshotCondition` rows, but the table is `ConditionsTable::configure()`, built for stored `Condition`. `CartSnapshotCondition` has no `display_name` or `is_active` columns, so those searchable/sortable columns render empty and throw SQL errors on sort/search. `recordActions()` (verified alias that resets) replaces the row Edit/Delete actions, but `bulkActions(deleteSelected)` is inherited and calls `ConditionResource::canDelete()` → `isGlobalRecordOutsideExplicitGlobalContext(Condition $record)` (typed param) plus `authorizeCondition(Condition ...)`, so bulk-deleting in this tab fatals with TypeError on `CartSnapshotCondition` records.
Evidence: `protected static string $relationship = 'cartConditions'; ... return ConditionsTable::configure($table)->headerActions([...])->recordActions([RemoveConditionAction::make()]);` vs `ConditionsTable` columns `display_name`, `is_active` and bulk action calling typed `Condition` helpers.
Recommendation: Build a dedicated snapshot-conditions table (columns limited to fields on `CartSnapshotCondition`: name/type/target/value/order/operator/flags/parsed_value) with only `RemoveConditionAction` row/bulk actions; drop the inherited stored-condition bulk delete.
Confidence: high

2) severity: high | category: bug | packages/filament-cart/src/Listeners/SendCartAbandonedNotification.php:9,28
Title: Hard dependency on aiarmada/checkout which is not in composer require
Description: The listener references `AIArmada\Checkout\Models\CheckoutSession::query()` directly, but composer.json requires only commerce-support, cart, filament. In this monorepo checkout is present so it works, but a standalone install fatals (uncatchable `Error`) whenever `CartAbandoned` fires, since the provider always registers the listener. This also violates the package's "adapter only" boundary by encoding checkout-session recovery logic.
Evidence: `use AIArmada\Checkout\Models\CheckoutSession; ... CheckoutSession::query()->where('cart_id', ...)` with no `class_exists` guard and no `aiarmada/checkout` in require.
Recommendation: Either add the dependency, or guard with `class_exists` + config flag and return early, or move recovery lookup behind an optional contract/event in cart/checkout.
Confidence: high

3) severity: medium | category: security | packages/filament-cart/src/Listeners/SendCartAbandonedNotification.php:62-69 + src/Notifications/CartAbandonedNotification.php:45
Title: Unvalidated retry URL and subject inputs in abandonment email
Description: `payment_redirect_url` (stored per-session, gateway-influenced) is used verbatim as the mail button `:url`, enabling an open-redirect/phishing link if the value is ever attacker-influenced; fallback `config('app.url')` is fine. Separately, `offer_name` (cart item name, merchant/user input) is interpolated into the mail subject without stripping CR/LF.
Evidence: `if (is_string($session->payment_redirect_url) ...) return $session->payment_redirect_url;` → `<x-mail::button :url="$retryUrl">`; `->subject(sprintf('Your %s checkout ...', $offerName))`.
Recommendation: Accept only http(s) URLs whose host is in an allowlist (else fall back to a signed in-app recovery route); strip `\r\n` from subject parts; also validate scheme before rendering the button.
Confidence: med

4) severity: medium | category: bug | packages/filament-cart/src/Listeners/SendCartAbandonedNotification.php:39-44
Title: Purchaser email used without format validation
Description: `billing_data['email']` is only checked `is_string` non-empty, then passed to `Notification::route('mail', ...)`. A malformed address fails on the queue worker with retries instead of failing fast/skipping. Listener is also synchronous, adding 2 queries + dispatch to the abandonment request path.
Evidence: `$purchaserEmail = $billingData['email'] ?? null; if (! is_string(...) ...) return; Notification::route('mail', $purchaserEmail)->notify(...)`.
Recommendation: `filter_var($purchaserEmail, FILTER_VALIDATE_EMAIL)` guard; consider ShouldQueue on the listener.
Confidence: high

5) severity: medium | category: performance | packages/filament-cart/src/Pages/CartDashboard.php:46-66
Title: Abandoned-cart count query runs twice per navigation render
Description: `getNavigationBadge()` and `getNavigationBadgeColor()` each call `getAbandonedCartCount()`, doubling an identical filtered count on every page load.
Evidence: Both methods call `self::getAbandonedCartCount()` with no memoization.
Recommendation: Memoize per-request (static local or `OwnerCache::remember` short TTL).
Confidence: high

6) severity: medium | category: performance | packages/filament-cart/src/Resources/CartResource/Tables/CartsTable.php:207-232
Title: Bulk clear/delete loops N authorize+resolve+sync cycles with no chunking or transaction
Description: Each selected cart triggers `authorizeCart` (extra SELECT) + `resolveForSnapshot` + full `clear()/destroy()`+sync; a mid-loop exception aborts leaving a partially processed selection with no per-row error reporting.
Evidence: `$records->each(function (Cart $record) { $cart = self::authorizeCart($record); app(CartInstanceManager::class)->resolveForSnapshot($cart)->clear(); })`.
Recommendation: Chunk with a per-row try/catch summary notification, or dispatch a queued bulk job; wrap each row idempotently.
Confidence: high

7) severity: medium | category: performance | packages/filament-cart/src/Resources/CartItemResource/Tables/CartItemsTable.php:36,46 + src/Resources/CartItemResource.php:58-64
Title: N+1 parent cart load in items table money formatting
Description: Every row calls `$record->cart->currency` in `formatStateUsing`, and `getEloquentQuery()` never eager-loads `cart`, so each page costs one extra query per row.
Evidence: `fn ($state, $record) => self::formatMoney(..., $record->cart->currency ?? null)`; query only adds `whereIn('cart_id', ...)` subquery.
Recommendation: Add `->with('cart')` (or `->eagerLoadRelations()`) in `CartItemResource::getEloquentQuery()` / relation manager query.
Confidence: high

8) severity: medium | category: bug | packages/filament-cart/src/Widgets/CartStatsWidget.php:48,78
Title: Total Value stat sums minor units across mixed currencies
Description: `sum('total')` aggregates all owner carts regardless of currency, then formats with the default currency — misleading whenever more than one currency exists.
Evidence: `$totalValue = (int) (clone $base)->where('items_count','>',0)->sum('total'); ... Stat::make('Total Value', $this->formatMoney($totalValue))` where `formatMoney` uses `CartMoney::formatMinor($amount)` default currency.
Recommendation: Group by currency (one stat per currency or dominant-currency + count), or scope the widget to a single currency filter.
Confidence: high

9) severity: medium | category: bug | packages/filament-cart/src/Resources/CartResource/Schemas/CartForm.php:28-31,76-80,129-132 + Pages/CreateCart.php + Pages/EditCart.php
Title: Cart form validation contradicts DB contract; create/edit pages are dead code
Description: `identifier ->unique()` is global, but the core migration uniques `(owner_scope, identifier, instance)` — cross-owner false collisions. Item `price` is `numeric()` with no min, allowing negatives; condition `value` is `numeric()`, rejecting the `%` syntax the core supports. `CreateCart`/`EditCart` exist with save paths that never assign an owner, but `CartResource::getPages()` registers only index/view with `canCreate/canEdit=false`, so they are unreachable dead code that will misbehave if ever wired up.
Evidence: `TextInput::make('identifier')->required()->unique(ignoreRecord: true)`; `TextInput::make('value')->numeric()->required()`; `getPages()` returns only index+view.
Recommendation: Scope the unique rule by owner_scope+instance (mirroring the migration), add `minValue(0)` on price, accept `%` values or drop the repeater, and either delete the dead pages or give them owner assignment + write guards before registering.
Confidence: high

10) severity: low | category: bug | packages/filament-cart/src/Widgets/RecentActivityWidget.php:67-89
Title: limit(50) fights pagination; selectRaw drops owner attributes from hydration
Description: `limit(50)` on a `paginated([10])` table silently caps the dataset at 50 rows with confusing paginator totals. The raw select also omits owner columns, so hydrated rows lack owner attributes for any downstream policy/display use (the `forOwner` WHERE itself still applies).
Evidence: `->selectRaw("id, identifier as session_id, ...")->orderByDesc(...)->limit(50); $query->forOwner(...)`.
Recommendation: Remove `limit(50)` (rely on pagination) or convert to a non-paginated `limit(50)->get()` list; include owner columns in the select.
Confidence: med

11) severity: low | category: bug | packages/filament-cart/src/Actions/ApplyConditionAction.php:53,106,274 + src/Actions/RemoveConditionAction.php:75,124
Title: Cart resolution throws outside try/catch → 500 instead of Filament error
Description: `resolveCartRecord()` (throws `InvalidArgumentException` / 404 from `OwnerWriteGuard`) is called before the `try` in all apply/clear paths; e.g. an orphan item (`$record->cart === null`) in `makeForItem` throws uncaught.
Evidence: `$cart = self::resolveCartRecord($record, $livewire); try { ... } catch ...`.
Recommendation: Move resolution inside the `try` (or wrap with a danger notification + return).
Confidence: high

12) severity: low | category: performance | packages/filament-cart/src/Resources/CartItemResource.php:81-84 + src/Resources/CartResource.php:87-92 + src/Resources/ConditionResource.php:98-103
Title: Navigation badges run uncached counts on every render; items badge shows "0"
Description: Three resources + dashboard each issue count queries per request with no cache. `CartItemResource` additionally casts unconditionally to string, rendering a "0" badge instead of hiding.
Evidence: `return (string) self::getEloquentQuery()->count();` vs siblings returning `null` when 0.
Recommendation: Return `null` on 0 and cache/memoize badge counts (e.g. `OwnerCache::remember`, 60s like `CartStatsWidget`).
Confidence: high

13) severity: low | category: performance | packages/filament-cart/src/Actions/ApplyConditionAction.php:131-142
Title: Condition options load entire active catalog into the modal on every open
Description: `getConditionOptions()` does an unbounded `->get()` + groupBy per modal render; fine for dozens of conditions, heavy for large catalogs.
Evidence: `$query->orderBy('type')->orderBy('name'); return $query->get()->groupBy('type')->...`.
Recommendation: Use a searchable relationship-driven select with limit, or cap + `getSearchResultsUsing`.
Confidence: med

14) severity: low | category: bug | packages/filament-cart/src/Resources/ConditionResource/Tables/ConditionsTable.php:53-60
Title: Target column match arms never match real stored targets
Description: The formatter matches `'subtotal'/'total'/'item'`, but stored targets are `'cart@cart_subtotal/aggregate'` etc., so the raw long string is always shown via `ucfirst` default.
Evidence: `match ($state) { 'subtotal' => ..., 'total' => ..., 'item' => ..., default => ucfirst($state) }`.
Recommendation: Map the real `cart@.../items@...` values (same labels as the form/filter).
Confidence: high

15) severity: low | category: bug | packages/filament-cart/src/Services/CartDownloadService.php:18-24,30-43
Title: Silent JSON-encode fallback; export relies solely on caller for authorization
Description: `json_encode(...) ?: '{}'` masks encoding failures with an empty-looking export. Payload includes full items/conditions/metadata (PII); the service itself performs no owner check — currently safe only because `ViewCart::export_cart` authorizes first.
Evidence: `echo json_encode($payload, ...) ?: '{}';` with no `JSON_THROW_ON_ERROR`; no guard in `download()/payload()`.
Recommendation: Throw on encode failure; document caller-must-authorize (or accept an already-authorized snapshot type / assert inside).
Confidence: med

16) severity: low | category: performance | packages/filament-cart/src/Resources/ConditionResource/Pages/EditCondition.php:31-48
Title: "Remove from All Carts" runs synchronously in the request
Description: `RemoveStoredConditions::handle()` fans out across all matching carts inline; large fleets will hit request timeouts with no progress feedback.
Evidence: Header action calls `->handle($record)` directly and reports counts in a notification.
Recommendation: Dispatch a queued job and notify on completion.
Confidence: med

AUTH NOTE (low/info): `LiveDashboardPage::canAccess()` checks only config, and there are no policies — any authenticated panel user can view dashboards and (via tables/actions) clear/delete carts and apply conditions. Filament v5 `EditRecord::authorizeAccess()` does honor `ConditionResource::canEdit()` (verified in vendor), so the global-record edit block holds including direct URLs. Consider panel-role checks if non-admin staff get panel access.

POSITIVES (brief): Owner scoping is consistently correct — all three resources scope reads (`forOwner` / cart_id subquery for items), every write path revalidates via `OwnerWriteGuard`, and core `ApplyStoredCondition`/`RemoveStoredConditions` re-authorize again defense-in-depth; global-condition edit/delete gates are enforced on pages too. No SQL injection (raw fragments are static; `ILIKE ?` and LIKE values are bound). Money stays int minor units via `CartMoney`. Filament navigation uses `config(navigation.group)` + `getNavigationGroup` per repo rules. Download filename is traversal-sanitized; Blade output is escaped (`{{ }}`); no `unserialize`, SSRF sinks, FK violations (no migrations), SoftDeletes, or Octane-unsafe static state; singletons are stateless. Core migrations index every column this package filters/sorts (abandoned/started/activity/identifier/instance/totals). Pagination is bounded; stats widget caches via owner-scoped `OwnerCache`. Test coverage exists under tests/src/FilamentCart (actions scoping, download service, widgets, notification listener).