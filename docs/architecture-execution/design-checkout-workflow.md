# Design Record: Checkout Workflow & Contributor Seam

- **Task:** DES-CHK-110
- **Date:** 2026-07-12
- **Status:** Proposed
- **Chosen design:** Design B — Frozen Registry with Container-Tagged Contributors

## Observed facts

1. **Duplicate execution algorithm:** `CheckoutService::processStepInternal()` (lines 331-364) and `RunCheckoutPipeline::processStep()` (lines 76-109) implement identical dependency checking, validation, state tracking, and event dispatch logic. The only difference is call-site wrapping: processStepInternal is used for single-step execution, RunCheckoutPipeline for full-pipeline iteration.

2. **Hardwired dependencies via `new`:** CheckoutService constructor (lines 48-55) instantiates `RunCheckoutPipeline` and `FinalizeCheckoutSession` with `new`, bypassing the container. Neither can be replaced or tested in isolation.

3. **Mutable singleton registry with public mutation API:** `CheckoutStepRegistry` exposes `replace()`, `insertBefore()`, `insertAfter()`, `enable()`, `disable()`, `setOrder()`. Under Octane/long-lived workers, mutations from one request survive into the next.

4. **Two providers mutate the same registry during boot:**
   - `CheckoutServiceProvider::registerDefaultSteps()` (lines 162-176) registers 8 lazy core steps.
   - `CheckoutServiceProvider::registerOptionalIntegrations()` (lines 178-190) applies config-driven enable/disable and order normalization via `CheckoutStepOrderPolicy`.
   - `EventsServiceProvider` (lines 281-322) resolves the same registry singleton and calls `replace()` / `insertAfter()` to insert `create_event_registrations` and `issue_event_passes` steps.

5. **Repeated provider boot is unsafe:** Under request-cycle or test container rebuilds, `registerDefaultSteps` and `registerOptionalIntegrations` re-execute, calling `registerLazy` on the same mutable registry. `registerLazy` always appends to `order[]` (line 46: `$this->order[] = $identifier`), so repeated boot duplicates steps.

6. **Dead external API methods:** `processStep()`, `getCurrentStep()`, and `canProceed()` are declared in `CheckoutServiceInterface` and documented in the Facade, but grep confirms zero external callers outside `packages/checkout/src`. The docs (04-usage.md) DO show these methods as the primary API.

7. **Docs advertise registry as extension point:** `packages/checkout/docs/05-checkout-steps.md` and `08-integrations.md` instruct users to call `app(CheckoutStepRegistryInterface::class)` and call mutation methods directly (register, replace, setOrder).

8. **Finalization is duplicated:** `CheckoutService::processCheckout()` calls `$this->finalizer->finalize($session)` (line 122), and `continueFromStep()` also calls `$this->finalizer->finalize($session)` (line 374). Same `FinalizeCheckoutSession` is called from two paths.

## Inferences

1. `processStep()`, `getCurrentStep()`, and `canProceed()` are implementation details accidentally exposed on the interface. No caller outside the package uses them. They can be removed from the external interface without breaking any production code — only docs need updating.

2. The external interface should reduce to domain-level operations: `startCheckout`, `resumeCheckout`, `processCheckout`, `retryPayment`, `cancelCheckout`, `handlePaymentCallback`. Step-level coordination is internal.

3. The mutable registry IS the contributor seam — Events uses it to insert steps. Removing it entirely without a replacement seam would break the Events integration and any documented user customizations.

4. Under Octane, the registry's state mutates across providers and persists across requests. The fix must make assembly repeatable (idempotent boot) and step resolution consistent regardless of boot order.

5. `RunCheckoutPipeline` is a private implementation detail of `CheckoutService`'s step-execution algorithm. It should not be separately instantiable.

## Design alternatives

### Design A: Contributor contract, hidden registry

Remove `CheckoutStepRegistryInterface` from the public API entirely. Replace with a `CheckoutContributor` interface:

```php
interface CheckoutContributor {
    /** @return array<StepDefinition> */
    public function steps(): array;
}
```

Packages implement this interface and register themselves via container tagging (`checkout.contributors`). During boot, the service provider resolves all tagged contributors, collects their `StepDefinition` payloads into an immutable `StepGraph`, and injects it into the executor.

**External interface:** `startCheckout`, `resumeCheckout`, `processCheckout`, `retryPayment`, `cancelCheckout`, `handlePaymentCallback` (6 methods, down from 9).

**Pros:** Clean separation, no mutable registry in public API, discoverable via container tags, step order is validated at graph-build time.
**Cons:** Breaking change for documented registry usage, Events must be migrated to the contributor contract, migration period required for docs promise, highest implementation cost.

### Design B: Frozen registry with container-tagged contributors (Chosen)

Keep `CheckoutStepRegistryInterface` but freeze it after boot. Add container-tagged contributor discovery as a parallel (replacing direct mutation from Events):

- The registry accumulates steps during `registeringPackage` / `bootingPackage` via its existing `register` / `registerLazy` API.
- After boot completes, the registry is frozen: mutation methods (`replace`, `insertBefore`, `insertAfter`, `setOrder`) throw if called.
- `CheckoutStepRegistryInterface` loses the mutation methods from the interface (move them to a `MutableStepRegistryInterface` used only internally).
- Contributors are discovered via container tag `checkout.steps` — Events registers its step definitions through its service provider as tagged services, not by directly mutating the singleton.
- The boot sequence: (1) core registers defaults, (2) tagged contributors register their steps, (3) order policy normalizes, (4) registry freezes.
- `RunCheckoutPipeline` is absorbed into `CheckoutService` as a private method; `FinalizeCheckoutSession` is injected via constructor instead of `new`.

**External interface:** Same 6-method reduction as Design A. `CheckoutStepRegistryInterface` becomes a READ-ONLY interface exposed for diagnostics/reporting only.

**Pros:** Preserves registry for documented customization (read-only access), `getOrderedSteps()`, `has()`, `isEnabled()` remain available for dashboard/tooling. Events migrated to tagged contributor. Idempotent boot — repeated provider loads don't duplicate steps if the registry checks for existing identifiers. Lower migration cost than Design A.
**Cons:** Two concepts (registry + contributor tag) exist during transition. Events must adopt the tag.

### Design C: Compiled step graph, no registry

Remove `CheckoutStepRegistry` and `CheckoutStepRegistryInterface` entirely. Replace with a `StepGraph` value object built during boot:

```php
final readonly class StepGraph {
    /** @param list<CheckoutStepInterface> $steps */
    public function __construct(private array $steps) {}
    public function ordered(): array { return $this->steps; }
    public function withStep(string $id, CheckoutStepInterface $step): self { ... }
}
```

Steps are collected from core and contributors into an immutable graph at boot time. The graph is injected into `CheckoutService`. No registry API at all — just a compiled list of ordered steps.

**Pros:** Simplest final state — one immutable object, no registry pattern, no mutation concerns.
**Cons:** Highest breaking change. Removes documented registry extension entirely. Events would need a completely new integration. All docs references to registry would be invalid. Migration surface is largest.

## Comparison

| Dimension | A (Contributor, hidden registry) | B (Frozen registry + tags) | C (Compiled graph) |
|-----------|----------------------------------|----------------------------|---------------------|
| Depth | Deep: registry removed from public API | Deep: registry frozen, step queries retained | Deepest: no registry at all |
| Leverage | Medium: removes observable mutable state | High: reuses documented API, freezes state | Low: rewrites documented extension |
| Locality | Medium: new contributor interface | High: registry stays, tagged service addition | Low: new graph value object |
| Caller knowledge | Low: contributors auto-discovered | Low: contributors auto-discovered | Very low: compile-only |
| Test surface | Must test contributor discovery + graph build | Must test freeze + tag discovery + existing registry | Must test new graph builder |
| Migration cost | High: remove registry from docs, migrate Events | Medium: keep read-only registry, migrate Events to tags | Very high: replace entire extension model |
| Compatibility | Breaking: registry removed from public | Breaking: registry mutation removed from public | Breaking: registry removed entirely |

## Chosen design

**Design B — Frozen Registry with Container-Tagged Contributors.**

### Rationale

1. **Preserves documented extension points:** `getOrderedSteps()`, `has()`, `isEnabled()` remain on the interface for dashboard/reporting/tooling. Mutation is removed from the public contract.

2. **Container-tagged contributors replace direct registry mutation:** Events registers its step definitions via Laravel's container tags. The service provider resolves all tags once during first boot invocation and calls `registerLazy` on the still-mutable registry. After registration, the registry freezes. This eliminates the cross-provider mutation and the repeated-boot duplication.

3. **Single execution algorithm:** `RunCheckoutPipeline` is absorbed into `CheckoutService` as a private step-iteration method. The duplicated `processStepInternal` / `RunCheckoutPipeline::processStep` converge into one private method.

4. **Idempotent boot:** `registerLazy` checks `has($identifier)` before appending to order, making repeated `registeringPackage` / `bootingPackage` calls safe under Octane.

5. **Dependencies injected:** `FinalizeCheckoutSession` is resolved from the container instead of instantiated with `new`. The frozen step list (from the registry post-boot) is passed explicitly rather than accessed from a mutable global.

### Seam definition

- **Internal seam:** `CheckoutContributor` interface (tag `checkout.steps`) — packages return `array<StepDefinition>` during service provider boot.
- **Adapter 1 (core Checkout):** `CheckoutServiceProvider` registers 8 default steps during `registerDefaultSteps()`.
- **Adapter 2 (Events):** `EventsServiceProvider` implements the contributor interface instead of directly mutating the registry. Steps are discovered via container tags, not hardcoded provider logic.

### Invariants

- The external interface exposes 6 methods: `startCheckout`, `resumeCheckout`, `processCheckout`, `retryPayment`, `cancelCheckout`, `handlePaymentCallback`.
- `processStep()`, `getCurrentStep()`, `canProceed()` are removed from `CheckoutServiceInterface`.
- `CheckoutStepRegistryInterface` exposes only read operations: `get()`, `has()`, `all()`, `getOrderedSteps()`, `getOrder()`, `getEnabledStepIdentifiers()`, `isEnabled()`.
- Mutation methods (`replace`, `insertBefore`, `insertAfter`, `enable`, `disable`, `setOrder`, `register`, `registerLazy`) move to an internal `MutableStepRegistryInterface`.
- `CheckoutService` does not instantiate collaborators with `new`.
- Repeated provider boot produces the same unique ordered steps (no duplication).

### External interface (post-design)

```php
interface CheckoutServiceInterface {
    public function startCheckout(string $cartId, ?string $customerId = null): CheckoutSession;
    public function resumeCheckout(string $sessionId): CheckoutSession;
    public function processCheckout(CheckoutSession $session): CheckoutResult;
    public function retryPayment(CheckoutSession $session): CheckoutResult;
    public function cancelCheckout(CheckoutSession $session): CheckoutSession;
    public function handlePaymentCallback(CheckoutSession $session, string $callbackType, array $payload = []): CheckoutResult;
}
```

### Errors, transactions, retries

- Step execution runs inside the session's database transaction (CheckoutService already wraps processCheckout in `DB::transaction`).
- Step failures trigger rollback of completed steps via existing `rollbackCompletedSteps()`.
- Retry: `retryPayment()` resets the payment step state and re-runs from that point via the unified step executor.

## Implementation scope manifest

### Files to create
- `packages/checkout/src/Contracts/StepContributor.php` (new — contract for tagged step providers)
- `packages/checkout/src/Services/StepExecutor.php` (new — single step-execution algorithm, absorbs RunCheckoutPipeline)

### Files to modify
- `packages/checkout/src/Contracts/CheckoutServiceInterface.php` — remove `processStep`, `getCurrentStep`, `canProceed`
- `packages/checkout/src/Contracts/CheckoutStepRegistryInterface.php` — split: keep reads, move mutation to MutableStepRegistryInterface
- `packages/checkout/src/Services/CheckoutService.php` — inject pipeline/finalizer, absorb step iteration, remove processStep/getCurrentStep/canProceed
- `packages/checkout/src/Services/CheckoutStepRegistry.php` — add freeze flag, implement MutableStepRegistryInterface, make registerLazy idempotent
- `packages/checkout/src/CheckoutServiceProvider.php` — discover tagged contributors, freeze registry after boot
- `packages/checkout/src/Facades/Checkout.php` — remove processStep, getCurrentStep, canProceed docblocks
- `packages/checkout/src/Support/CheckoutStepOrderPolicy.php` — ensure idempotent order normalization
- `packages/events/src/EventsServiceProvider.php` — migrate from direct registry mutation to tagged contributor

### Files to delete
- `packages/checkout/src/Services/RunCheckoutPipeline.php` — absorbed into StepExecutor

### Tests to create/update
- `tests/src/Checkout/StepExecutorTest.php` (new — unified step execution)
- `tests/src/Checkout/CheckoutServiceProviderTest.php` — add idempotent boot, freeze, contributor discovery tests
- `tests/src/Checkout/CheckoutStepRegistryTest.php` — add freeze/immutable tests
- `tests/src/Checkout/PaymentFlowTest.php` — verify processCheckout still works with reduced interface
- `tests/src/Events/EventCheckoutIntegrationTest.php` (new) — verify tagged contributor registers steps correctly

### Docs to update
- `packages/checkout/docs/04-usage.md` — replace processStep/getCurrentStep/canProceed examples with processCheckout
- `packages/checkout/docs/05-checkout-steps.md` — replace direct-registry-mutation examples with tagged contributor
- `packages/events/README.md` — document tagged contributor migration

## Rejected alternatives

### Design A (Contributor, hidden registry)
Rejected: removes all public registry access, breaking documented read-only use cases (dashboard step inspection, debugging). A read-only diagnostic surface is harmless and useful.

### Design C (Compiled graph)
Rejected: rewrites the entire extension model for a marginal gain (immutable graph vs frozen registry). The registry pattern is well-understood by this codebase; replacing it with a graph value object adds cognitive overhead for zero functional benefit over Design B's freeze approach.

## Unknowns

- Whether any production deployment has published step-ordering docs and expects the mutable registry API to remain callable at runtime. The migration plan assumes read-only registry access is sufficient for all external consumers.
- Whether Events contributes steps in any path OTHER than the boot method observed (lines 281-322). If Events has a console command or job that mutates the registry at runtime, that path must also migrate to the contributor tag.

## Migration plan

1. Deprecate `processStep()`, `getCurrentStep()`, `canProceed()` on `CheckoutServiceInterface` — mark with `@deprecated`, log warnings on call.
2. Add `MutableStepRegistryInterface` for internal mutation; `CheckoutStepRegistryInterface` becomes read-only.
3. Implement `StepExecutor` absorbing `RunCheckoutPipeline`, inject into `CheckoutService`.
4. Add `StepContributor` interface and container tag discovery.
5. Migrate Events to tagged contributor; remove direct registry mutation from `EventsServiceProvider`.
6. Freeze registry after all contributors register.
7. Update docs.
8. In CHK-714 (final cleanup), remove deprecated methods entirely.
