End-to-end review of packages/cart (domain package: no routes/controllers/views; Filament UI lives in paired filament-cart).

POSITIVES (brief): uuid PKs everywhere; no DB FK constraints/cascades (foreignUuid without constrained()) and no SoftDeletes per repo rules; owner-scoping consistently via commerce-support (HasOwner/OwnerScope/OwnerContext/OwnerWriteGuard/OwnerScopeKey) on CartModel, Condition, CartSnapshot, DatabaseStorage, commands, jobs, and actions; money as int minor units with a single boundary (Support/CartMoney) and basis-point percentages; CAS optimistic locking with version column; input size limits (CartLimits) plus closure/resource serializability rejection; Octane state reset for cart bindings and preset statics; recursion guard for global-condition application via Context; abandonment command has dry-run/max-affected/confirm/all-owners safeguards; no eval/unserialize/SSRF/path-traversal sinks found; JSON decode failures fall back safely with logging.

FINDINGS:

1) severity: high | category: security | packages/cart/src/Support/LoginMigrationIdentifierResolver.php:56 + HandleUserLoginAttempt.php:17
Title: Guest-cart migration keyed by public login identifier allows cart-content injection into a victim's account
Description: On every login Attempting (including failed attempts), the current session id is cached under sha256(lower(email|username|phone)). On Login, whatever session id is cached under the victim's identifier has its cart merged into the victim's account. An attacker who knows the victim's email can start a login attempt from the attacker's own session (caching attackerSession under victimEmail); when the victim logs in within the 5-minute TTL, the attacker's guest items/conditions/metadata are merged into the victim's cart. Item payloads (id/name/price/attributes) are attacker-controlled at the package level via add(), so this is a cross-account integrity violation (price/name confusion at checkout). Cache::pull also makes the first Login consume the entry, breaking multi-guard or retry logins.
Evidence: `Cache::put(LoginMigrationCacheKey::make($identifier), $sessionId, ...)` on Attempting; `Cache::pull(...)` then `migrationAction->execute($user, $instance, $sessionId)` on Login.
Recommendation: Do not key pending migrations by a remotely-supplied identifier. Instead stash a random one-time migration token in the guest's own session pre-login and consume it post-login from that same session (session fixation-safe: read before ID regeneration), or verify the cached session id is not attacker-planted by binding it to a secret stored in the guest session. At minimum, only migrate when the cached session differs from any authenticated session and log the merge.
Confidence: med (injection path is fully in-package; price-impact severity depends on whether the storefront sets prices server-side).

2) severity: medium | category: security | packages/cart/src/CartManager.php:230
Title: CartManager::swap() silently drops owner scope via withOwner(null)
Description: swap() unconditionally calls `$this->storage->withOwner(null)`, so an owner-scoped manager (e.g. `$manager->forOwner($x)->swap(...)`) operates on global-scope carts instead of the owner's carts when cart.owner.enabled=true. Combined with swapIdentifier's destructive target delete, this can read, overwrite, and delete another scope's carts.
Evidence: `$storage = $this->storage->withOwner(null); $swapped = $storage->swapIdentifier(...)`.
Recommendation: Use `$this->storage` as-is in swap() so the current owner scope is preserved; if cross-scope swap is ever needed, require an explicit owner argument plus explicit-global assertion.
Confidence: high.

3) severity: medium | category: bug | packages/cart/src/Storage/DatabaseStorage.php:352
Title: swapIdentifier() unconditionally deletes the target cart (silent data loss)
Description: The swap transaction first deletes any existing cart at the target identifier, then re-points the source row. A swap into an identifier that already has items silently destroys the target cart's items/conditions/metadata with no merge, no event, and no return signal distinguishing it. The pre-transaction has() check is also outside the transaction (TOCTOU).
Evidence: `$this->baseQuery($newIdentifier, $instance)->delete(); ... update(['identifier' => $newIdentifier ...])`.
Recommendation: Refuse or merge on target conflict (return false / throw / reuse merge strategies) instead of deleting; move the existence check inside the transaction with lockForUpdate.
Confidence: high.

4) severity: medium | category: bug | packages/cart/src/Storage/DatabaseStorage.php:710 + Snapshots/NormalizedCartSynchronizer.php:51
Title: Concurrent first-writes race the unique key and surface raw QueryException instead of a domain conflict
Description: performCasUpdate()'s insert path and NormalizedCartSynchronizer::syncFromCart()'s firstOrNew()->save() both assume absence-then-insert. Two concurrent requests creating the same (owner_scope, identifier, instance) cart/snapshot hit the unique index and throw an unhandled QueryException (HTTP 500) rather than CartConflictException or a retry-as-update.
Evidence: `$this->database->table($this->table)->insert($insertData);` with no unique-violation handling; `$cartModel = ...->firstOrNew([...]); ... $cartModel->save();`.
Recommendation: Catch the unique-violation (code 23000) and retry once as a version-checked update, or use upsert/atomic insert-ignore-then-update semantics.
Confidence: high.

5) severity: medium | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:67
Title: Guest→user migration is not atomic; partial failure leaves duplicated/diverged carts
Description: execute() performs ~5 separate writes (putItems, putConditions, putMetadataBatch, markSourceCartAsMerged, guest forget) with no encompassing transaction. A failure or CAS conflict midway (e.g. conditions update throws) leaves items merged but the guest cart not deleted, or metadata half-merged, causing duplicate items on retry/login and divergent snapshots.
Evidence: sequential `$this->resolveStorage()->putItems/putConditions/putMetadataBatch(...)`, then `markSourceCartAsMerged(...)`, then `$guestStorage->forget(...)` with no DB::transaction wrapper.
Recommendation: Wrap the merge + source-mark + source-delete in one DB transaction (storage already supports CAS inside transactions) and make the operation idempotent on retry.
Confidence: high.

6) severity: medium | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:255
Title: mergeItems() writes raw merged quantities, bypassing max_item_quantity and item validation
Description: Merged quantities are computed by the strategy and written straight to storage via putItems(), which only validates item count and byte size — never per-item quantity. A merge can therefore produce quantities exceeding cart.limits.max_item_quantity (which add()/update() enforce via CartItem validation), and can propagate corrupt shapes (`$existingItem['quantity'] ?? 0` may be non-int).
Evidence: `$mergedItems[$itemId]['quantity'] = $newQuantity; ... putItems($userIdentifier, $instance, $mergedItems);`.
Recommendation: Clamp/validate merged quantities against CartLimits (and re-validate each merged row through CartItem) before persisting; surface capped quantities in the merged event.
Confidence: high.

7) severity: medium | category: bug | packages/cart/src/Actions/ApplyStoredCondition.php:64
Title: applyCustom() has no input validation; missing keys or bad target DSL crash with TypeError/uncaught exception
Description: applyCustom() reads `$data['name']`, `$data['type']`, `$data['target']`, `$data['value']` directly (undefined-key warning + TypeError on missing keys under strict types) and passes raw target strings into ConditionTarget::from(), whose InvalidArgumentException is uncaught (500). There is also no value sanity check (e.g. a custom 100% discount) — authorization is entirely delegated to the caller with no documented contract.
Evidence: `name: (string) $data['name'], type: (string) $data['type'], target: (string) $data['target'], value: $data['value']` with no isset/validation.
Recommendation: Validate required keys/types up front and throw domain Exception (like the sibling finders do); catch/normalize ConditionTarget failures; document the caller-auth contract (Filament policy) or accept an authorizer.
Confidence: high.

8) severity: medium | category: performance | packages/cart/src/Console/Commands/ClearAbandonedCartsCommand.php:278
Title: Snapshot abandonment marking loads all candidates unbounded and saves row-by-row (memory + N+1)
Description: processAbandonedSnapshots() calls ->get() with no chunking (all matching snapshots hydrated at once), then issues one UPDATE plus events per row via markAsAbandoned(). countAbandonedSnapshots() doubles the scan. Large backlogs risk OOM and long table pressure.
Evidence: `$snapshots = $this->abandonedSnapshotsQuery($minutes)->get(); foreach ($snapshots as $snapshot) { $snapshot->markAsAbandoned(); }`.
Recommendation: Process with chunkById()/lazyById (e.g. 500–1000) and a single bulk update for checkout_abandoned_at where per-row events are not required, keeping the max-affected guard.
Confidence: high.

9) severity: medium | category: performance | packages/cart/src/Console/Commands/ClearAbandonedCartsCommand.php:556
Title: Abandoned-cart deletion plucks ALL ids into memory before chunking
Description: processForOwner() does `$query->clone()->pluck('id')->chunk($batchSize)` — pluck() materializes every matching id in PHP memory first; chunk() then only splits the in-memory collection. --batch-size therefore does not bound memory on large carts tables.
Evidence: `steps: $query->clone()->pluck('id')->chunk($batchSize)`.
Recommendation: Use chunkById()/lazyById over the base query (or delete in ranged batches) instead of pluck-then-chunk.
Confidence: high.

10) severity: medium | category: performance | packages/cart/src/Snapshots/SyncCartOnEvent.php:37 + Snapshots/CartSyncManager.php:17
Title: Every cart mutation dispatches a sync job with no coalescing; jobs can overtake and stale-write snapshots
Description: All 10 cart/item/condition events trigger CartSyncManager::sync(), which (with default queue_sync=true) dispatches one SyncNormalizedCartJob per event. Bulk edits (addMultiple, refreshBuyablePrices, condition syncs) flood the cart-sync queue, and concurrent jobs for the same cart run last-writer-wins with no version check — an older job can overwrite a newer snapshot (stale items/totals), or collide on the unique key (see #4).
Evidence: `$dispatcher->listen([...10 events...], SyncCartOnEvent::class)`; `SyncNormalizedCartJob::dispatch(...)` unconditionally; job is not ShouldBeUnique and synchronizer ignores versions.
Recommendation: Make the job ShouldBeUnique (by owner+identifier+instance) or debounce/coalesce (dispatch after response / unique-until-processed), and add a version/updated_at guard so stale jobs no-op.
Confidence: med (flood is certain; stale-overwrite requires concurrent workers).

11) severity: medium | category: performance | packages/cart/src/Traits/ManagesStorage.php:128
Title: Associated-model restoration issues one unscoped Eloquent query per cart item (N+1 + cross-scope read)
Description: getItemsFromStorage() calls restoreAssociatedModel() per item, which runs `$className::find($id)` for any stored class+id pair. A 50-item cart costs 50 extra queries on every getItems()/total/content call, the find() is not owner-scoped, and the class name comes from stored JSON (any Model subclass is instantiated/returned into the cart object graph).
Evidence: `if (isset($associatedData['id']) && is_subclass_of($className, Model::class)) { return $className::find($associatedData['id']); }`.
Recommendation: Batch-resolve (group ids by class, one whereIn per class), apply the current owner scope to the lookup, and consider allow-listing buyable model classes; cache resolved models per request.
Confidence: high.

12) severity: medium | category: bug | packages/cart/src/Traits/ManagesItems.php:311 + Traits/ManagesBuyables.php:168
Title: Bulk paths amplify writes: addMultiple() and refreshBuyablePrices() do one read+write+events+sync-job per item
Description: addMultiple() calls addItemInternal() per row (each: getItems, save, invalidate, 1–2 events → sync jobs), so a 100-item import costs ~100 DB read-modify-writes and ~100+ queued snapshot syncs; a mid-loop validation failure also leaves a partially imported cart. refreshBuyablePrices() similarly calls update() per changed item.
Evidence: `foreach ($items as $item) { $cartItem = $this->addItemInternal(...); }`; `foreach (...) { $this->update($item->id, ['price' => $newPrice]); }`.
Recommendation: Add a batch path that loads once, applies all rows in memory, saves once, and dispatches one sync; wrap in a transaction for all-or-nothing imports.
Confidence: high.

13) severity: medium | category: bug | packages/cart/src/Traits/ManagesItems.php:28 + Traits/ManagesItems.php:82
Title: add()/update() accept malformed quantity shapes and fail with TypeError/arithmetic errors instead of domain exceptions
Description: addMultiple() reads `$item['id']` without isset (missing key → null → TypeError in addItemInternal's string|int param). update() treats `$data['quantity']` as relative delta with no type check: a float/string/array-without-value flows into `setQuantity(int)` (TypeError) or `$item->quantity + $quantity` (unsupported-operand TypeError for non-numeric strings). Callers get 500s instead of InvalidCartItemException.
Evidence: `$item['id'], $item['name'] ?? null, ...` (id not null-safe); `$newQuantity = $item->quantity + $quantity; ... setQuantity($newQuantity)`.
Recommendation: Validate id/quantity shape and numeric-int type at the top of add/update and throw InvalidCartItemException; cast only validated numeric strings.
Confidence: high.

14) severity: low | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:184
Title: Swap-path merge attribution never records merged_into_id (mark called before target exists)
Description: In swapIdentifierWithStorage(), markSourceCartAsMerged() runs before the target cart is written. Its target lookup therefore finds nothing and returns early, so guest→user swaps via this path never set merged_into_id — unlike the merge path where the target already exists. Abandonment/audit queries on merged_into_id silently miss these migrations.
Evidence: `$this->markSourceCartAsMerged(...); $targetStorage->putItems(...);` (mark precedes insert).
Recommendation: Mark after writing the target (or pass the created target id through), matching the merge-path ordering.
Confidence: high.

15) severity: low | category: bug | packages/cart/src/Actions/MigrateGuestCartToUserAction.php:303
Title: sumItemQuantities() assumes well-formed item arrays; corrupt storage rows cause TypeError
Description: The reducer is typed `fn (int $sum, array $item)` and reads `$item['quantity'] ?? 0` without numeric checks. A corrupt items payload (non-array row, string quantity) throws TypeError — uncaught on the execute() path (only executeForUser() catches Exception).
Evidence: `array_reduce($items, static fn (int $sum, array $item) => $sum + ($item['quantity'] ?? 0), 0)`.
Recommendation: Guard with is_array/is_numeric checks and cast to int, consistent with the defensive reads elsewhere.
Confidence: high.

16) severity: low | category: security | packages/cart/src/Storage/DatabaseStorage.php:169
Title: flush() in an explicit-global context truncates every owner's carts
Description: When owner-scoped storage is null (explicit global), flush() calls truncate() on the whole carts table, wiping all tenants' carts — not just global-scope rows. Blast radius is limited by the testing/local-environment gate, but a local/dev command or test helper with owner enabled still destroys cross-tenant data.
Evidence: `if ($this->ownerType !== null ...) { ...delete(); } else { $query->truncate(); }`.
Recommendation: Always delete with the owner predicate applied (global-only predicate when global), never truncate; or refuse flush entirely when cart.owner.enabled=true.
Confidence: high.

17) severity: low | category: performance | packages/cart/src/Models/Traits/AssociatedModelTrait.php:31
Title: Full associated-model toArray() is persisted into cart JSON but never used on restore
Description: getAssociatedModelArray() embeds the model's entire toArray() (plus class+id) into every cart item's stored JSON, inflating items payloads toward the 1MB cap and persisting stale (and potentially sensitive, hidden-respecting but still broad) snapshots. restoreAssociatedModel() ignores the embedded data and re-fetches by class+id anyway.
Evidence: `['class' => ..., 'id' => ..., 'data' => ...->toArray()]` persisted; restore uses only `['class','id']`.
Recommendation: Persist only class+id (+optional display snapshot fields), or drop the data key.
Confidence: high.

18) severity: low | category: bug | packages/cart/src/Storage/DatabaseStorage.php:547 + Models/Condition.php:733
Title: Unbounded recursion in serializability/context normalization runs before size checks (deep-nesting DoS)
Description: validateSerializable() recurses without a depth cap and is called before the byte-size check, so a deeply nested but small payload can exhaust the stack. Condition::normalizeContextValue() has the same shape and additionally splits any comma-containing string into arrays and JSON-decodes bracket strings, which can surprise legitimate values ('a,b' becomes ['a','b']).
Evidence: `validateSerializable($value, $type, $currentPath)` recursive with no depth param; `normalizeContextValue()` recursive + `explode(',', $trimmed)`.
Recommendation: Add a max-depth constant (e.g. 32) to both; make comma-splitting opt-in per key rather than implicit.
Confidence: med.

19) severity: low | category: bug | packages/cart/src/Models/CartItem.php:186 + Traits/ManagesItems.php:413
Title: Price string normalization strips commas, making '1,000' ambiguous (1000 minor units, not 1000 major)
Description: ',' is stripped before numeric parsing, so '1,000' becomes 1000 minor units (RM 10.00) while '10.50' is treated as major→minor (1050). Thousand-separated major-unit input is silently interpreted 100× too small.
Evidence: `str_replace([... ',', ' '], '', $normalized)` then `str_contains($normalized, '.') ? minorFromDecimal : (int)`.
Recommendation: Document that comma input is unsupported and reject it, or parse thousand separators as major units consistently.
Confidence: med.

20) severity: low | category: bug | packages/cart/src/Snapshots/CartSnapshot.php:279 + Listeners/HandleUserLogin.php:54
Title: Minor hardening gaps: unscoped user() relation; login listener assumes web session
Description: (a) CartSnapshot::user() belongsTo(identifier→id) ignores owner scope and mis-resolves guest session-string identifiers. (b) HandleUserLogin calls session()->flash() unconditionally; in console/queue-authenticated Login events the session store may be unavailable, and it iterates an unbounded getInstances() list per login.
Evidence: `belongsTo($userModel, 'identifier', 'id')`; `session()->flash('cart_migration', ...)`; `$guestStorage->getInstances($sessionId)` loop.
Recommendation: Scope or remove user(); guard session access with app()->bound('session')/runningInConsole checks; cap instances processed per login.
Confidence: med.

NOTED AS UNRESOLVED/OUT-OF-SCOPE: No XSS/SSRF/traversal/deserialization sinks in this package (rendering lives in filament-cart; stored names/attributes are intentionally unescaped — consumers must escape on output). No package-level Pest tests exist to cross-check behavior; full-suite verification was out of scope for this review task.