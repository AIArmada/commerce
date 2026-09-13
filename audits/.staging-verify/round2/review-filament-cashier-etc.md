# End-to-end review: filament-cashier, ticketing, pricing, filament-chip

## Positives (brief)
- Ticketing models consistently use `HasOwner+HasOwnerScopeConfig`, uuid PKs, no FK cascades, `TicketingOwnerGuard` on saving; migrations use `uuid+index`, `nullableMorphs`.
- Pricing models enforce owner write-guards (`OwnerContext::resolve`, `belongsToOwner` checks) and validate scoped refs; money in int minor units.
- Filament-chip `SendInstruction`/`BankAccount` tables re-validate owner via `forOwner()->whereKey()` before destructive actions; `CreateSendInstruction` re-checks verified status.
- Filament-cashier `CreateSubscription` re-validates billable + payment-method ownership server-side; portal `ManageSubscriptions::findSubscription` scopes by `OwnerScopedQuery` + billable.
- Blade views use `{{ }}` escaping; no `{!! !!}`, `DB::raw` with user input, `unserialize`, `eval`, path traversal found.

---

## ticketing

**1. critical/security — `packages/ticketing/src/Actions/TransferPassToHolderAction.php:17` + `BulkTransferPassesAction.php:24` + `Services/DefaultPassTransferService.php:18` — Transfer actions never enforce `PassTransferPolicy`/Gate**
Description: `PassTransferPolicy::transfer` checks `isValid`, expiry, current-holder ownership, and is registered via `Gate::policy(Pass::class)`, but neither action nor service calls `Gate::authorize/can` or the policy. Any caller with action access can transfer any pass, including expired/invalid or others' passes (subject only to global owner scope).
Evidence: `TransferPassToHolderAction::handle(){ $resolvedHolder=...; return app(PassTransferServiceInterface::class)->transfer(...);}` with no auth; `DefaultPassTransferService::transfer` only checks `canTransfer` (valid+expiry).
Recommendation: Call `Gate::authorize('transfer',$pass)` (or inject policy) in both actions; also enforce in service as defense-in-depth. Add tests for cross-holder/expired denial.
Confidence: high.

**2. high/security — `packages/ticketing/src/Actions/BulkTransferPassesAction.php:58` — Bulk event/job uses first pass owner only; mixed-owner bulk possible if scope bypassed**
Description: After `whereIn()->get()` (owner-scoped by default), event/job context is `$passes->first()?->owner_*`. If caller runs in explicit-global or scope-disabled context, passes of mixed owners can be bulk-transferred in one txn and notified under wrong owner context.
Evidence: `$event=new PassesBulkTransferred($passes,$newHolders->first(),$reason); $ownerType=$passes->first()?->owner_type; dispatch(new BulkSendTransferNotificationsJob(event:$event,ownerType:$ownerType,...))`.
Recommendation: Reject mixed `owner_type/owner_id` sets; derive job context per-pass or require single-owner batch.
Confidence: med.

**3. high/bug — `packages/ticketing/src/Actions/TransferPassToHolderAction.php:29` + `Services/DefaultPassTransferService.php:33` — Pre-saved holder + `pass_id` overwrite allows hijack/orphans**
Description: `resolveHolder` `save()`s a new `PassHolder(is_current=true)` before `transfer()` runs. If `transfer` then throws, orphan current holder remains (single-transfer path has no outer txn). If caller passes an existing `PassHolder` from another pass, `transfer` blindly does `$newHolder->pass_id=$pass->getKey()` and saves, stealing the holder row.
Evidence: `if($newHolder instanceof PassHolder) return $newHolder;` then in service `$newHolder->pass_id=$pass->getKey(); $newHolder->is_current=true; $newHolder->save();`.
Recommendation: Validate `$newHolder->pass_id===null||===$pass->id`, wrap resolve+transfer in one txn, add `selectForUpdate` on current holder, unique partial index `(pass_id) where is_current`.
Confidence: high.

**4. high/performance — `packages/ticketing/src/Services/DefaultPassIssuer.php:23` + `Listeners/IssuePassesOnOrderPaid.php:35` — Unbounded `quantity` issuance DoS**
Description: `issuePassesFor` loops `quantity` with no max; `IssuePassesOnOrderPaid` passes `$item->quantity` directly. Large quantity allocates `quantity` models + pass numbers in memory and inserts in one txn; `generatePassNumbers` has `while(true)` retry loops.
Evidence: `if($context->quantity<=0) return ...; $passNumbers=$this->generatePassNumbers($context->quantity); foreach...makePass` ; `quantity:$item->quantity`.
Recommendation: Cap quantity (e.g. config `max_issue_quantity`, default 100-500), chunk inserts, bound retry loops.
Confidence: high.

**5. medium/bug — `packages/ticketing/src/Services/DefaultPassIssuer.php:155` — Bulk `insert()` bypasses events; only first pass owner-guarded**
Description: `insertPasses` uses `Pass::query()->insert($passes->map->getAttributes())`, skipping `saving/creating` observers, audit, casts. Only `firstPass` is passed to `TicketingOwnerGuard::assertRelations`.
Evidence: `TicketingOwnerGuard::assertRelations($firstPass,...); $this->insertPasses($passes);` + `Pass::query()->insert(...)`.
Recommendation: Guard all (or assert single owner) and document event bypass; consider `createMany` or fire audit manually.
Confidence: high.

**6. medium/security — `packages/ticketing/src/Actions/IssuePassesAction.php:53` — Holder attributes mass-assigned without validation**
Description: `name/email/holder_type/holder_id` taken verbatim from `PassIssuanceContext` (which `IssuePassesOnOrderPaid` fills from cart `options['participants']`, user-controlled). No email format/length checks; arbitrary `holder_type` morph strings accepted (guard only checks existence/HasOwner).
Evidence: `$holder->name=$holderAttributes['name']??null; $holder->email=...; if(isset(...['holder_type'],...['holder_id'])){$holder->holder_type=...;}`.
Recommendation: Validate via FormRequest/Data rules (email, max lengths, allow-list morph types), or resolve holder models server-side.
Confidence: high.

**7. medium/bug+performance — `packages/ticketing/src/Jobs/BulkSendTransferNotificationsJob.php:41` — N+1 + wrong `previousHolder` in bulk emails**
Description: Loops `$event->passes` doing `$pass->holder` per pass (N+1). Event carries only `$newHolders->first()` as `toHolder`, then job does `$transferredFrom=$event->toHolder??$holder` and passes it as `previousHolder` to `PassTransferredToNewHolderNotification($pass,$transferredFrom)`, so every email shows the first new holder as “Transferred by”.
Evidence: `foreach($this->event->passes as $pass){ $holder=$pass->holder; $transferredFrom=$this->event->toHolder??$holder; notify(new ...( $pass,$transferredFrom,...));}`.
Recommendation: Eager-load holders, store per-pass previous-holder map in event, fix arg order.
Confidence: high.

**8. medium/bug — `packages/ticketing/src/Listeners/SendTransferNotifications.php:19` — No blank-email guard (vs delivery service has it)**
Description: Directly `Notification::route('mail',$previousHolder->email)->notify(...)` for both holders. If either email null/blank, `route('mail',null)` errors or mis-sends. `DefaultPassDeliveryService:22` correctly does `if(blank($holder->email)) return;`.
Recommendation: Mirror blank checks; skip or log.
Confidence: high.

**9. medium/security — `packages/ticketing/src/Actions/AddTicketTypeToCartAction.php:105` — `extraAttributes` can override system attrs; `participants` unbounded**
Description: `return array_merge($attributes,$extraAttributes)` lets caller override `purchasable_type/id, inventoryable_*, code`. `participants` merged without size/shape validation and persisted in cart.
Evidence: `$attributes=[purchasable_type...,participants...]; ... return array_merge($attributes,$extraAttributes);`.
Recommendation: Allow-list extra keys, reject collisions, cap participants count/size and validate emails.
Confidence: med.

**10. medium/performance — `packages/ticketing/src/Models/TicketType.php:294` — `getTotalAvailable()` loads all levels**
Description: `inventoryLevels()->get()->sum(fn=> $level->available)` hydrates all rows + accessor per row; called from cart validation (`hasInventory`) per add.
Evidence: `return (int)$this->inventoryLevels()->get()->sum(static fn(InventoryLevel $l)=> $l->available);`.
Recommendation: Aggregate in SQL (`sum(quantity_on_hand - reserved)` or scopes), add covering index.
Confidence: high.

**11. medium/bug — migrations missing uniqueness/indexes — `packages/ticketing/database/migrations/2000_01_01_000001_create_ticket_types_table.php:20`, `..._000002_:17`, `..._000004_:31`**
Description: `code` is `index()` not unique; `EnsureTicketTypeAction:19` uses `firstOrNew(ticketable+code)` so concurrent ensures duplicate. `ticket_type_components` has no composite unique `(parent,component)`. `passes.transfer_expires_at` (used by `ExpireTransfersCommand:26` range scan) has no index.
Recommendation: Add `unique(ticketable_type,ticketable_id,code)`, `unique(parent,component)`, `index(transfer_expires_at)`.
Confidence: med.

## pricing

**12. high/bug — `packages/pricing/database/migrations/2000_12_01_000002_create_prices_table.php:15`, `..._000001_:25`, `..._000003_:15` — `foreignUuid` creates DB FKs, violating “no FK” rule**
Description: `foreignUuid('price_list_id')`, `foreignUuid('customer_id')->nullable()`, etc. create real foreign-key constraints. Repo rule forbids DB FKs/cascades (ticketing correctly uses `uuid+index`).
Evidence: `$table->foreignUuid('price_list_id'); $table->foreignUuid('customer_id')->nullable();`.
Recommendation: Replace with `$table->uuid(...)->index()` (+ app-level existence checks already present).
Confidence: high.

**13. high/bug — `packages/pricing/src/Services/PriceCalculator.php:181` + `Support/CustomerPriceResolver.php:19`, `SegmentPriceResolver.php:19`, `TierResolver.php:15` — Currency ignored in resolution**
Description: `calculate` reads `$currency` from context/config but `getPriceListPrice`/customer/segment/tier queries never filter by `currency`. A list in another currency can win and its minor-unit amount is returned under the requested currency.
Evidence: `$currency=Arr::get($context,'currency')...;` then `PriceList::query()->default()->...->first()` and `Price::where(price_list_id,...)->first()` with no currency predicate.
Recommendation: Filter lists/prices/tiers by currency (or convert explicitly); add test with multi-currency lists.
Confidence: high.

**14. high/bug — `packages/pricing/src/Actions/ApplyPromotionalAdjustment.php:67` — Stub cart/item breaks promotion targeting**
Description: Builds anonymous cart/item where `getAttribute()` always returns null and items lack type/price/category. Real `PromotionService` targeting on product attributes will never match; `promotionable_type/id` only smuggled via `metadata` the service may ignore.
Evidence: `getAttribute(string $key):mixed{return null;}` + `TargetingContext(cart:$cart, metadata: [...promotionable_type...])`.
Recommendation: Pass real priceable/cart shape or extend promotion contract to accept explicit target; add integration test.
Confidence: med.

**15. medium/performance — `packages/pricing/src/Services/PriceCalculator.php:70` — 4–5 queries per `calculate`, no batch/cache**
Description: Every item triggers customer→segment→tier→promotion→pricelist queries + `PriceCalculated::dispatch`. Cart with N items = 5N queries, plus promotion service call. No memoization or `calculateMany`.
Recommendation: Add batch API, request-scoped memo for lists/tiers, optional cache with owner+effective_at key.
Confidence: high.

**16. low/performance — missing indexes for hot scopes — `packages/pricing/src/Models/Price.php:202`, `PriceList.php:146`, `PriceTier.php:23`**
Description: `scopeActive` filters `deactivated_at`, `scopeDefault` filters `is_default`, tier lookup filters `is_active`, none indexed (only `starts/ends`, `is_active+priority` exist).
Recommendation: Add `index(deactivated_at)`, `index(is_default)`, `index(is_active)` / composite with existing keys.
Confidence: med.

## filament-cashier

**17. high/bug — `packages/filament-cashier/src/Resources/UnifiedSubscriptionResource/Pages/ListSubscriptions.php:134` + `UnifiedInvoiceResource/Pages/ListInvoices.php:110` — Admin lists show only current user**
Description: Both `getAllSubscriptions/Invoices` branch on `auth()->user() instanceof BillableContract` then call `Cashier::gateway($g)->subscriptions/invoices($user)`. Admin sees own subscriptions, not owner-scoped all; tabs/badges/counts likewise wrong.
Evidence: `$user=auth()->user(); if(!$user instanceof BillableContract...) return collect(); ...Cashier::gateway($gateway)->subscriptions($user);`.
Recommendation: Query owner-scoped subscription/invoice models (like widgets do via `OwnerScopedQuery`) for admin; keep per-user query only for portal.
Confidence: high.

**18. medium/performance — `packages/filament-cashier/src/CustomerPortal/Pages/ManageSubscriptions.php:71` + `Support/CustomerSubscriptionsQuery.php:31` — Unbounded `loadMore`**
Description: `loadMoreSubscriptions(int $increment=50)` does `$this->perGatewayLimit+=max(1,$increment)` with Livewire-exposed int, no max. Each render fetches `limit+1` per gateway and sorts in PHP.
Evidence: `public function loadMoreSubscriptions(int $increment=self::DEFAULT_LOAD_MORE_INCREMENT):void{ $this->perGatewayLimit+=max(1,$increment);}`.
Recommendation: Clamp increment/total (e.g. max 200), validate, paginate server-side.
Confidence: high.

**19. medium/security — `packages/filament-cashier/src/Widgets/TotalMrrWidget.php:69`, `TotalSubscribersWidget.php:29`, `GatewayBreakdownWidget.php:87`, `GatewayComparisonWidget.php:35` — `once()` without owner key**
Description: All use global `once(fn)` with no key. Within one request/job with owner switching (Octane, jobs, multi-panel), second owner gets first owner’s cached totals. Also hides per-user differences.
Evidence: `return once(function():array{ $detector=app(GatewayDetector::class); ... OwnerScopedQuery::apply(...)->chunk...});`.
Recommendation: Key by owner (`once(fn, OwnerContext::cacheKey())` / `OwnerCache`) or drop `once` in favor of property memo.
Confidence: med.

**20. medium/bug — `packages/filament-cashier/src/Widgets/TotalMrrWidget.php:36` — Currency conversion inverted for non-USD base**
Description: `if(display_converted){ foreach... if($currency!==$base && isset($rates[$currency])) $primaryMrr+=(int)($amount/$rates[$currency]);}`. With config `MYR=>4.70,USD=>1.00` and base `MYR`, USD amount divides by 1 (no conversion); correct is `*4.7`. Only correct when base is USD.
Recommendation: Store rates vs base consistently (`amount * rate[target]/rate[source]`) and test both bases.
Confidence: med.

**21. high/performance — `packages/filament-cashier/src/Widgets/GatewayComparisonWidget.php:98` — 12 full chunk scans per render**
Description: `getMonthlyDataForGateway` loops 6 months × gateways, each doing `OwnerScopedQuery->with('items')->whereBetween(...)->chunk(200)` + `UnifiedSubscription::from*` per row. Dashboard render = up to 12 table scans.
Recommendation: Single grouped query per gateway (or pre-aggregated MRR table), cache 5–15 min.
Confidence: high.

**22. medium/security+performance — `packages/filament-cashier/src/Pages/GatewayManagement.php:67` — Live gateway probes on every render + leaky errors + global Stripe key mutation**
Description: `getGatewayHealth()` maps `availableGateways()` → `checkStripeHealth` (`Stripe::setApiKey($secret); Account::retrieve(); finally restore`) and `checkChipHealth` (`getAccountBalance()`) synchronously, no cache. Failures return `$e->getMessage()` to UI (can leak SDK/config details). Mutating global `Stripe::setApiKey` is Octane-unsafe.
Evidence: `Stripe::setApiKey($secret); try{Account::retrieve();}finally{Stripe::setApiKey(...);}` + `catch(Exception $e){return [...'message'=>$e->getMessage()];}`.
Recommendation: Cache health 60s+, generic user message + log detail, use per-request Stripe client instead of global.
Confidence: high.

**23. medium/performance — `packages/filament-cashier/src/CustomerPortal/Pages/ViewInvoices.php:41` — Unbounded invoice fetch**
Description: Iterates all `$user->invoices()` / `chipInvoices()` with no limit/pagination, sorts in PHP. Heavy users stall portal.
Recommendation: Paginate/limit (e.g. 50 + load-more), cache per user.
Confidence: med.

**24. medium/security — `packages/filament-cashier/src/Resources/UnifiedInvoiceResource/Tables/InvoicesTable.php:94` — Export bulk action has no authz; fragile date assumption**
Description: `BulkAction export` streams CSV for any selected `UnifiedInvoice`s with no `SubscriptionPolicy`-style ownership check (unlike subscription bulk cancel). Also `$invoice->date->format()` assumes non-null Carbon.
Evidence: `BulkAction::make('export')->action(fn(Collection $records):StreamedResponse=>... $invoice->date->format('Y-m-d')...)` with no policy call.
Recommendation: Authorize each invoice (billable match), null-guard date, escape CSV formula prefixes.
Confidence: med.

## filament-chip

**25. high/performance — `packages/filament-chip/src/Widgets/ChipStatsWidget.php:72`, `PaymentMethodsWidget.php:50`, `TokenStatsWidget.php:50`, `RevenueChartWidget.php:67` — Unbounded `->get()` + PHP sums**
Description: Revenue/breakdown widgets do `Purchase::query()->forOwner()->where(...)->get()` then `sum($purchase->purchase['total'])` per period. `ChipStats` runs 3 such scans + 2 counts per render; `PaymentMethods` scans all paid purchases; 30-day chart loads all rows. Memory grows with volume.
Evidence: `$purchases=$query->get(); return $purchases->sum(fn(Purchase $p)=>(int)($p->purchase['total']??0));`.
Recommendation: Aggregate in SQL (JSON extracts per driver like `PurchaseTable:131` does), add date/status indexes, cache 60–300s.
Confidence: high.

**26. medium/security — `packages/filament-chip/src/Pages/AnalyticsDashboardPage.php:16` — Livewire `period` injection**
Description: `public string $period='30'` + `updatedPeriod()->loadMetrics()` with `$startDate=$endDate->subDays((int)$this->period)` and no validation. User can set `999999`/negative via Livewire, causing huge `getDashboardMetrics/getRevenueTrend` scans.
Evidence: `public string $period='30'; ... $startDate=$endDate->subDays((int)$this->period);`.
Recommendation: Validate `in:7,30,90` (or int 1..365), cast + clamp.
Confidence: high.

**27. medium/performance — `packages/filament-chip/src/Resources/BaseChipResource.php:39` — Badge `count()` per resource per nav render**
Description: `getNavigationBadge()` does `(int)static::getEloquentQuery()->count()` for each of 6 resources on every page load, no cache.
Recommendation: Cache counts 30–60s per owner or disable badges.
Confidence: high.

**28. medium/bug — `packages/filament-chip/src/Actions/PurchaseExporter.php:45` — `bool` type-hint crashes on int; sensitive `checkout_url` exported**
Description: `->formatStateUsing(fn(bool $state):string=>...)` with `strict_types=1` TypeErrors when DB returns `0/1`/null for `is_test`. Export also includes `checkout_url` (signed payment URL) + `client_id` without redaction.
Evidence: `ExportColumn::make('is_test')->formatStateUsing(fn(bool $state):string=> $state?'Yes':'No')` + `ExportColumn::make('checkout_url')`.
Recommendation: Accept `mixed` and cast, drop/redact `checkout_url`, scope export query by owner.
Confidence: med.

**29. medium/security — `packages/filament-chip/src/Widgets/ChipStatsWidget.php:128`, `RevenueChartWidget.php:121`, `RecentTransactionsWidget.php:91` — Silent global fallback when no owner**
Description: `if(resolve()!==null||isExplicitGlobal()) return $cb(); return withOwner(null, $cb);` renders cross-owner revenue/transactions for any Filament user with no owner context, without explicit-global permission check.
Recommendation: Require `OwnerContext::isExplicitGlobal()` + capability; otherwise return empty/unauthorized.
Confidence: med.

**30. low/performance — `packages/filament-chip/src/Resources/ClientResource.php:124`, `PaymentResource.php:116` — Distinct filter options uncached**
Description: Country/currency `SelectFilter::options(fn()=> Client/Payment::query()->forOwner()->distinct()->pluck...)` runs on every table render.
Recommendation: Cache 5–15 min per owner.
Confidence: high.
