## Second pass — 2026-06-09

### Confirmed

- `Actions/MigrateGuestCartToUserAction` exists as the canonical migration entrypoint.
- `Services/CartMigrationService` still exists as a compatibility wrapper.
- `Enums/CartMergeStrategy` enum, `Contracts/CartMergeStrategyInterface`, and `Services/CartMergeStrategyRegistry` all exist.
- `Services/CartFactory` and `Conditions/Pipeline/ConditionPipelineFactory` exist for centralized cart/pipeline construction.
- `Support/LoginMigrationIdentifierResolver` exists for shared identifier resolution.
- `Actions/MigrateCartOnLoginAction` exists for the login migration workflow.
- `Conditions/Handlers/ConditionTypeHandlerInterface` and `ConditionTypeHandlerRegistry` exist as the special condition-type handler seam (replacing the hard-coded shipping singleton).
- `Services/RulePresets` delegates to `BuiltInRulesFactory` as canonical — confirmed via class docblock.
- Tests exist at `tests/src/Cart/` with comprehensive coverage (~70 test files).

### Still open

None. All items resolved.

### Resolved

- `CartMigrationService` stripped to thin wrapper (delegates to `MigrateGuestCartToUserAction`). (2026-06-09)
- `CartManager::swap()` now uses `$this->storage->withOwner(null)->swapIdentifier(...)` directly. (2026-06-09)
- `LazyConditionPipelineTest` extended with custom phase processor and scope resolver parity tests. (2026-06-09)

### Updated recommendation

All Phase 1 and Phase 4 findings resolved.

---

# Cart package friendliness review

Reviewed on 2026-06-07.

Scope: `packages/cart` with supporting checks in `tests/src/Cart`.

## Overall take

`cart` already has a few strong extension seams:

- `StorageInterface` keeps persistence swappable.
- `ConditionProviderInterface` + `ConditionProviderRegistry` let other packages contribute conditions without hard dependencies.
- `RulesFactoryInterface` gives dynamic conditions a metadata-driven restoration seam.
- Cart lifecycle events are already a useful hook surface.
- `ConditionPipeline` exposes scope resolvers and phase processors, which is the right direction.

The biggest friendliness problems are not the absence of seams. They are:

1. duplicated orchestration,
2. stringly typed branching where variants are likely to grow,
3. hard-coded object construction that bypasses the seams the package already has, and
4. a lazy pipeline path that does not honor the same extension model as the eager path.

## Findings

### 1. Guest-cart migration orchestration exists twice, and one copy appears unused

Relevant files:

- `src/Services/CartMigrationService.php`
- `src/Actions/MigrateGuestCartToUserAction.php`
- `src/CartManager.php`
- `tests/src/Cart/Feature/Migration/MigrationTest.php`
- `tests/src/Cart/Unit/Services/MigrationServiceTest.php`

What I found:

- `CartMigrationService` and `MigrateGuestCartToUserAction` both implement the same merge workflow:
  - load guest and user items,
  - merge quantities,
  - merge conditions,
  - merge metadata,
  - forget the guest cart,
  - dispatch `CartMerged`.
- The helper methods are duplicated in both classes (`resolveQuantityConflict()`, merge helpers, conflict detection, quantity summing).
- `CartManager::swap()` sidesteps the container and does `new Services\CartMigrationService([], $this->storage)` directly.
- A workspace search found no first-party references to `MigrateGuestCartToUserAction` outside its own file and cache artifacts, which makes it look like dead or abandoned parallel architecture.

Why it hurts friendliness:

- Future changes need to be made twice or they drift.
- Callers do not have one obvious orchestration entrypoint.
- The package already prefers reusable orchestration; this is the opposite.

Recommendation:

- Pick **one** cart-owned orchestration entrypoint and make everything else delegate to it.
- Prefer a real cart use case / action as the canonical entrypoint, then turn `CartMigrationService` into a thin compatibility wrapper or remove it after callers migrate.
- Keep this in `cart`, not `commerce-support`, because guest-cart migration is cart business behavior.

### 2. Merge strategies are stringly typed and duplicated

Relevant files:

- `src/Services/CartMigrationService.php`
- `src/Actions/MigrateGuestCartToUserAction.php`
- `config/cart.php`
- `tests/src/Cart/Feature/Migration/MigrationTest.php`

What I found:

- Merge behavior is currently driven by string config values such as:
  - `add_quantities`
  - `keep_highest_quantity`
  - `keep_user_cart`
  - `replace_with_guest`
- The branching logic is duplicated in both migration implementations.
- The event payload also reports the strategy as a raw string.

Why it hurts friendliness:

- New variants require editing branching code in-place.
- The public configuration surface is broader than the actual extension seam.
- External packages cannot add a custom merge rule cleanly.

Recommendation:

- Introduce a cart-owned merge seam such as:
  - `CartMergeStrategy` enum for the built-ins, and
  - `CartMergeStrategyInterface` + registry for optional custom handlers.
- Resolve config to a strategy object once, then let the migration action call that strategy.
- Only move anything to `commerce-support` if another package later needs the exact same strategy registry pattern. Today this still looks cart-owned.

### 3. Hard-coded object construction weakens the existing seams

Relevant files:

- `src/CartManager.php`
- `src/Cart.php`
- `src/Traits/ManagesInstances.php`
- `src/Traits/HasLazyPipeline.php`
- `src/Conditions/Pipeline/LazyConditionPipeline.php`

What I found:

- `CartManager` creates new `Cart` objects in several places.
- `ManagesInstances::setInstance()` also creates a new `Cart` directly.
- `Cart::evaluateConditionPipeline()` does `new ConditionPipeline`.
- `HasLazyPipeline::getLazyPipeline()` does `new LazyConditionPipeline($context)`.
- `LazyConditionPipeline` itself defaults to `new ConditionPipeline`.
- The `Cart` constructor accepts an optional `ConditionProviderRegistry`, but the clone-style construction paths do not pass it through consistently.

Why it hurts friendliness:

- It makes customization harder than the API suggests.
- It quietly drops injectable state during instance switching.
- It blocks alternate pipeline implementations, instrumentation, or test doubles from flowing naturally through the package.

Recommendation:

- Add a cart-owned `CartFactory` and `ConditionPipelineFactory`.
- Route all cart cloning / instance switching / pipeline construction through those factories.
- Make the factories responsible for preserving `conditionResolver`, `conditionProviderRegistry`, and any future cart-level collaborators.

### 4. The lazy pipeline does not honor the same extension points as the eager pipeline

Relevant files:

- `src/Conditions/Pipeline/ConditionPipeline.php`
- `src/Conditions/Pipeline/LazyConditionPipeline.php`
- `src/Traits/HasLazyPipeline.php`
- `tests/src/Cart/Unit/LazyConditionPipelineTest.php`

What I found:

- `ConditionPipeline` supports:
  - phase processors via `registerPhaseProcessor()`,
  - scope resolvers via `registerScopeResolver()`.
- `LazyConditionPipeline::evaluateFully()` uses the eager pipeline.
- But `LazyConditionPipeline::evaluatePartially()` uses its own `resolvePhaseAmount()` implementation that just loops conditions and applies them directly.
- That means partial subtotal evaluation can bypass custom phase processors and custom scope resolvers entirely.
- Existing tests cover correctness for basic totals and cache behavior, but they do not pin parity with custom processors or custom scope resolvers.

Why it hurts friendliness:

- The seam exists, but the fast path does not truly respect it.
- Any package that extends the pipeline could see different behavior depending on whether the lazy path is taken.
- That is exactly the kind of extension surprise that makes a module hostile to future variants.

Recommendation:

- Extract a shared phase-execution engine that both eager and lazy paths use.
- Alternatively, teach the lazy path to delegate each phase back through the same resolver stack instead of re-implementing it.
- Add regression tests that compare lazy and eager results when a custom phase processor or scope resolver is installed.

### 5. Shipping is a hard-coded special condition type

Relevant files:

- `src/Traits/ManagesConditions.php`
- `tests/src/Cart/Feature/Conditions/ShippingConditionsTest.php`
- `tests/src/Cart/Unit/Traits/ManagesConditionsTest.php`

What I found:

- `addShipping()` removes existing shipping first.
- `removeShipping()` and `getShipping()` scan conditions with `getType() === 'shipping'`.
- The public API bakes in the idea that shipping is a singleton special case.

Why it hurts friendliness:

- If cart later needs similar singleton condition types such as insurance, gift wrap, handling, or packaging fees, the pattern will be copied again.
- The special behavior is embedded in trait logic instead of hanging off a named seam.

Recommendation:

- Introduce a cart-owned special-condition-type seam, for example a registry of handlers for singleton/special types.
- Keep `addShipping()`, `removeShipping()`, and `getShipping()` as the public convenience API, but have them delegate to the handler for `shipping`.
- Do **not** move this to `commerce-support` yet; it is still cart-specific condition behavior.

### 6. Login migration orchestration is split across listeners with duplicated identifier resolution

Relevant files:

- `src/Listeners/HandleUserLogin.php`
- `src/Listeners/HandleUserLoginAttempt.php`
- `src/Support/LoginMigrationCacheKey.php`
- `tests/src/Cart/Unit/Listeners/HandleUserLoginTest.php`
- `tests/src/Cart/Unit/Listeners/HandleUserLoginAttemptTest.php`

What I found:

- Both listeners contain their own `getUserIdentifiers()` logic.
- The cache-key handshake is spread across attempt-time and login-time listeners.
- The login listener also knows about flash-session messaging.

Why it hurts friendliness:

- The orchestration is harder to reuse from another entrypoint.
- Any change to identifier precedence has to be kept in sync across both listeners.
- Listener classes are doing real workflow work instead of just adapting events into a use case.

Recommendation:

- Extract a cart-owned `LoginMigrationIdentifierResolver` support class.
- Extract a thin `MigrateCartOnLoginAction` that owns the workflow.
- Leave listeners as small adapters:
  - attempt listener captures identifiers,
  - login listener delegates to the action and handles presentation concerns.

### 7. Rule logic has two public sources of truth

Relevant files:

- `src/Services/BuiltInRulesFactory.php`
- `src/Services/RulePresets.php`
- `src/Conditions/ConditionPresets.php`
- `docs/06-dynamic-conditions.md`

What I found:

- `BuiltInRulesFactory` contains a large, named rule catalog.
- `RulePresets` also contains a large catalog of public rule helpers that overlap heavily in intent with the factory.
- `ConditionPresets` consumes the factory directly.
- Public docs lean on `RulePresets`.

Why it hurts friendliness:

- The package has two different public ways to describe the same families of rules.
- Future rule additions risk landing in one path and not the other.
- The API surface becomes wider without adding real leverage.

Recommendation:

- Make one layer the source of truth.
- The friendliest shape would be:
  - `BuiltInRulesFactory` or a rule registry owns the canonical named rules,
  - `RulePresets` becomes a thin convenience layer over that source of truth.
- This stays in `cart`; it is a cart rule-definition concern.

## Keep and deepen

These seams are already doing useful work and should be preserved:

- `src/Storage/StorageInterface.php`
- `src/Contracts/ConditionProviderInterface.php`
- `src/Conditions/ConditionProviderRegistry.php`
- `src/Contracts/RulesFactoryInterface.php`
- `src/Events/*`
- `src/Conditions/Pipeline/Resolvers/ConditionScopeResolverInterface.php`

The main job is to make the runtime paths honor these seams consistently.

## What should stay in `cart` vs what might move later

### Should stay in `cart`

These are cart business rules or cart extension seams:

- guest-cart migration / merge orchestration,
- merge strategies,
- special condition-type handlers,
- cart and pipeline factories,
- login migration action and identifier resolver,
- rule preset consolidation.

### Only consider `commerce-support` later

Only extract to `commerce-support` if a second package proves the seam is genuinely cross-cutting.

Possible future candidates:

- a generic owner-batched maintenance helper if multiple packages duplicate the `ClearAbandonedCartsCommand` pattern,
- a generic authentication-identifier resolution helper if several packages need the same login-handoff mechanism.

I do **not** see a strong reason to move the main refactor targets into `commerce-support` right now.

## Concrete refactor plan

### Phase 0 — Characterization coverage first

Add or extend tests in `tests/src/Cart` before changing architecture:

- extend `tests/src/Cart/Unit/LazyConditionPipelineTest.php` to assert lazy/eager parity with:
  - a custom phase processor,
  - a custom scope resolver;
- extend `tests/src/Cart/Feature/Migration/MigrationTest.php` to cover:
  - items,
  - conditions,
  - metadata,
  - owner-scoped migration,
  - event payloads,
  - merge-strategy behavior through the eventual canonical entrypoint;
- add or extend a test that proves identifier precedence is shared between both login listeners;
- add a regression test that preserves current singleton shipping semantics while refactoring the implementation.

Done when:

- the current behavior is pinned well enough that the next phases can be mechanical.

### Phase 1 — Choose one canonical migration entrypoint

Target files:

- `src/Actions/MigrateGuestCartToUserAction.php`
- `src/Services/CartMigrationService.php`
- `src/CartManager.php`
- listeners that trigger migration

Steps:

1. Pick the canonical orchestration class.
2. Move all merge helpers into that class.
3. Make `CartMigrationService` either:
   - a thin compatibility wrapper, or
   - remove it once callers are updated.
4. Stop constructing the migration service manually in `CartManager::swap()`.

Done when:

- there is only one implementation of merge logic in first-party code.

### Phase 2 — Introduce a real merge-strategy seam

Target files:

- migration entrypoint from Phase 1,
- `config/cart.php`,
- new cart strategy classes.

Steps:

1. Add a `CartMergeStrategy` enum for built-in names.
2. Add a `CartMergeStrategyInterface` and registry.
3. Register built-in handlers for the current strategies.
4. Resolve config to a strategy object instead of branching inline.

Done when:

- adding a new merge rule no longer requires editing a `match` expression in the migration workflow.

### Phase 3 — Route cart and pipeline construction through factories

Target files:

- `src/CartManager.php`
- `src/Cart.php`
- `src/Traits/ManagesInstances.php`
- `src/Traits/HasLazyPipeline.php`
- pipeline classes

Steps:

1. Add a `CartFactory` that can clone a cart without losing collaborators.
2. Add a `ConditionPipelineFactory` for eager and lazy pipeline construction.
3. Replace direct `new Cart(...)`, `new ConditionPipeline`, and `new LazyConditionPipeline(...)` calls.
4. Ensure factories preserve `conditionResolver`, `conditionProviderRegistry`, and any future pipeline collaborators.

Done when:

- every runtime path that creates a cart or pipeline goes through a single construction seam.

### Phase 4 — Unify lazy and eager pipeline execution

Target files:

- `src/Conditions/Pipeline/ConditionPipeline.php`
- `src/Conditions/Pipeline/LazyConditionPipeline.php`
- `tests/src/Cart/Unit/LazyConditionPipelineTest.php`

Steps:

1. Extract shared phase execution logic or make lazy evaluation delegate to the same resolver stack.
2. Remove the duplicated partial-evaluation algorithm that bypasses phase processors / scope resolvers.
3. Verify parity using the new characterization tests.

Done when:

- lazy and eager totals behave identically for custom pipeline extensions.

### Phase 5 — Extract login migration helpers

Target files:

- `src/Listeners/HandleUserLogin.php`
- `src/Listeners/HandleUserLoginAttempt.php`
- new support/action classes in `src/Support` or `src/Actions`

Steps:

1. Extract shared identifier resolution into one support class.
2. Extract `MigrateCartOnLoginAction` for the actual workflow.
3. Keep listeners thin and event-focused.
4. Preserve existing session-flash behavior behind the action result.

Done when:

- identifier precedence is defined in one place,
- listeners stop being orchestration owners.

### Phase 6 — Replace the shipping special case with a condition-type handler seam

Target files:

- `src/Traits/ManagesConditions.php`
- new cart condition-type handler classes
- shipping tests

Steps:

1. Introduce a handler/registry abstraction for singleton or special condition types.
2. Implement `shipping` with the current behavior.
3. Keep the public shipping helpers as convenience methods.

Done when:

- adding another singleton condition type does not require duplicating the shipping pattern.

### Phase 7 — Collapse the duplicate rule catalogs

Target files:

- `src/Services/BuiltInRulesFactory.php`
- `src/Services/RulePresets.php`
- `src/Conditions/ConditionPresets.php`
- related docs/tests

Steps:

1. Decide which layer is canonical.
2. Make the other layer delegate to it.
3. Add parity tests for representative rule families.

Done when:

- a new named rule is defined in one place, not two.

## Recommended order

1. Phase 0 — characterization coverage
2. Phase 1 — one migration entrypoint
3. Phase 2 — merge strategy seam
4. Phase 3 — cart/pipeline factories
5. Phase 4 — lazy/eager parity
6. Phase 5 — login migration helpers
7. Phase 6 — special condition type seam
8. Phase 7 — rule catalog consolidation

## Short version

The package is already close to being extension-friendly, but it needs one cleanup pass around orchestration and construction.

If I had to start with only three changes, I would do these first:

1. consolidate guest-cart migration into one cart-owned action/use case,
2. replace stringly merge branching with a real strategy seam,
3. make the lazy pipeline honor the same extension points as the eager pipeline.


## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 0 — Characterization coverage first

- [done] extend `tests/src/Cart/Unit/LazyConditionPipelineTest.php` to assert lazy/eager parity with:
- [done] extend `tests/src/Cart/Feature/Migration/MigrationTest.php` to cover:
- [done] add or extend a test that proves identifier precedence is shared between both login listeners;
- [done] add a regression test that preserves current singleton shipping semantics while refactoring the implementation.
- [done] the current behavior is pinned well enough that the next phases can be mechanical.

### Phase 1 — Choose one canonical migration entrypoint

- [done] Pick the canonical orchestration class. — `MigrateGuestCartToUserAction`
- [done] Move all merge helpers into that class.
- [done] Make `CartMigrationService` either: — **Fixed 2026-06-09: stripped to thin wrapper delegating to MigrateGuestCartToUserAction.**
- [done] Stop constructing the migration service manually in `CartManager::swap()`. — **Fixed 2026-06-09: uses storage->withOwner(null)->swapIdentifier() directly.**

### Phase 2 — Introduce a real merge-strategy seam

- [done] Add a `CartMergeStrategy` enum for built-in names.
- [done] Add a `CartMergeStrategyInterface` and registry.
- [done] Register built-in handlers for the current strategies.
- [done] Resolve config to a strategy object instead of branching inline.

### Phase 3 — Route cart and pipeline construction through factories

- [done] Add a `CartFactory` that can clone a cart without losing collaborators.
- [done] Add a `ConditionPipelineFactory` for eager and lazy pipeline construction.
- [done] Replace direct `new Cart(...)`, `new ConditionPipeline`, and `new LazyConditionPipeline(...)` calls.
- [done] Ensure factories preserve `conditionResolver`, `conditionProviderRegistry`, and any future pipeline collaborators.

### Phase 4 — Unify lazy and eager pipeline execution

- [done] Extract shared phase execution logic or make lazy evaluation delegate to the same resolver stack.
- [done] Remove the duplicated partial-evaluation algorithm that bypasses phase processors / scope resolvers.
- [done] Verify parity using the new characterization tests.
- [done] Verify characterization tests actually cover custom phase processors and scope resolvers (not just basic totals). — **Fixed 2026-06-09: added `respects custom phase processors with lazy evaluation` and `respects custom scope resolvers with lazy evaluation` tests.**

### Phase 5 — Extract login migration helpers

- [done] Extract shared identifier resolution into one support class.
- [done] Extract `MigrateCartOnLoginAction` for the actual workflow.
- [done] Keep listeners thin and event-focused.
- [done] Preserve existing session-flash behavior behind the action result.

### Phase 6 — Replace the shipping special case with a condition-type handler seam

- [done] Introduce a handler/registry abstraction for singleton or special condition types.
- [done] Implement `shipping` with the current behavior.
- [done] Keep the public shipping helpers as convenience methods.

### Phase 7 — Collapse the duplicate rule catalogs

- [done] Decide which layer is canonical (BuiltInRulesFactory).
- [done] Make the other layer delegate to it (RulePresets delegates).
- [done] Add parity tests for representative rule families.


