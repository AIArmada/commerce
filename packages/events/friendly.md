# Events friendliness review

This note reviews `packages/events` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (7 classes)
- `src/Actions` (11 classes)
- `src/Resolvers` (13 classes)
- `src/Contracts` (16 classes)
- `src/Listeners` (5 classes)
- `src/Models` (15 classes)
- `src/Events` (17 classes)
- `src/Console/Commands`
- `src/Support`
- downstream consumers in `checkout`, `orders`, `customers`, `products`, `signals`

## What is already friendly

### Resolver contracts are real seams

- `Contracts/EventAssetResolver.php`
- `Contracts/EventChangeNoticeAudienceResolver.php`
- `Contracts/EventChangeNoticeWorkflow.php`
- `Contracts/EventChangeNoticeNotificationDispatcher.php`
- `Contracts/EventCheckoutIntentResolver.php`
- `Contracts/EventClassificationResolver.php`
- `Contracts/EventDisplayTimezoneResolver.php`
- `Contracts/EventLifecycleWorkflow.php`
- `Contracts/EventModerationWorkflow.php`
- `Contracts/EventOrderItemFulfillmentResolver.php`
- `Contracts/EventReferenceResolver.php`
- `Contracts/EventRelationalContentSubject.php`
- `Contracts/EventScheduleResolver.php`
- `Contracts/EventSearchEngine.php`
- `Contracts/EventSearchPayloadResolver.php`

This is exceptional. 16 contracts give the package deep extension seams. New resolver strategies are first-class classes.

### Null resolvers exist for optional integrations

- `Resolvers/NullEventChangeNoticeNotificationDispatcher.php`
- `Resolvers/NullEventCheckoutIntentResolver.php`
- `Resolvers/NullEventOrderItemFulfillmentResolver.php`
- `Resolvers/NullEventReferenceResolver.php`
- `Resolvers/NullEventScheduleResolver.php`

The package ships null-object resolvers for optional integrations. This is the right pattern for "feature may not be installed."

### Actions own the workflows

- 11 Actions under `src/Actions/`: `BackfillEventContentAction`, `CreateOccurrenceCartLineAction`, `CreateRegistrationsForOrderItemAction`, `EnsureOccurrenceAction`, `FinalizeOccurredEventOrdersAction`, `FulfillEventOrderAction`, `FulfillEventOrderItemAction`, `RecordEventEngagementAction`, `StartOccurrenceCheckoutAction`, `SyncEventOrderCompletionAction`, `SyncEventOrderRegistrationsAction`

The package follows the monorepo's "Actions only" rule correctly. This is the cleanest orchestration in the monorepo.

### Workflow contracts separate policy from implementation

- `Services/DefaultEventLifecycleWorkflow.php` (impl `Contracts/EventLifecycleWorkflow.php`)
- `Services/DefaultEventModerationWorkflow.php` (impl `Contracts/EventModerationWorkflow.php`)
- `Services/DefaultEventChangeNoticeWorkflow.php` (impl `Contracts/EventChangeNoticeWorkflow.php`)

Each workflow is a default implementation behind a contract. New workflows are additive.

### Listeners react to orders events

- 5 listeners under `src/Listeners/` reacting to `OrderPaid`, `OrderCanceled`, `OrderRefunded`, `RegistrationCheckedIn`

This is the right pattern — react to events, not direct calls.

## Findings

### 1. Service count (7) and Action count (11) are well-balanced

**Files in `src/Services/`**

- `EventQueryService` (read-side)
- `RegistrationService` (lifecycle)
- `EloquentEventSearchEngine` (impl `EventSearchEngine`)
- `EventContentSynchronizer`
- `DefaultEventLifecycleWorkflow` (impl contract)
- `DefaultEventModerationWorkflow` (impl contract)
- `DefaultEventChangeNoticeWorkflow` (impl contract)

**Why this is worth noting**

The service count is reasonable and each service has a clear role. The workflow contracts are correctly separated from the default implementations. This is the right shape.

**Recommendation**

Keep this discipline. Only add new services if a clear read-side or workflow contract is needed.

### 2. `EventContentSynchronizer` is a likely duplicate or near-duplicate of `BackfillEventContentAction`

**Files**

- `src/Services/EventContentSynchronizer.php`
- `src/Actions/BackfillEventContentAction.php`

**Why this hurts friendliness**

If these two classes do the same work (sync or backfill event content), they should be one class. The "service" vs "action" distinction is meaningless here.

**Recommendation**

Audit both files. Make one the canonical entry point and have the other delegate or be removed.

### 3. `RegistrationService` is a likely catch-all

**Files**

- `src/Services/RegistrationService.php`

**Why this hurts friendliness**

This is the only service without a contract. It likely owns many registration workflows. Callers depend on the concrete class.

**Recommendation**

Add `Contracts/RegistrationServiceInterface` or — better — split into Actions and keep the service as a thin facade.

### 4. Resolver count is high (13) and the Null pattern is good but undocumented

**Files**

- `src/Resolvers/*` (13 classes)
- 5 of them are Null objects

**Why this hurts friendliness**

The Null pattern is excellent, but the rule for "when to use Null vs when to use Default" is not documented. New resolvers need a clear convention.

**Recommendation**

Document the convention in `docs/04-usage.md` or in a top-of-folder comment. The pattern is: `Default*` for the built-in, `Null*` for the no-op fallback, both behind the same contract.

### 5. The `Support/` folder has a mix of policy and integration classes

**Files**

- `src/Support/CommerceIntegration.php` (wiring)
- `src/Support/ConfiguredEventModel.php`
- `src/Support/EventAddressRegistry.php`
- `src/Support/EventAddressResolver.php`
- `src/Support/EventContentNormalizer.php`
- `src/Support/EventLifecyclePolicy.php`
- `src/Support/EventModerationPolicy.php`
- `src/Support/LifecyclePolicy.php`

**Why this hurts friendliness**

The folder mixes integration wiring, normalization, and policy. Two policy classes (`EventLifecyclePolicy` and `LifecyclePolicy`) suggest overlap or staged naming.

**Recommendation**

Audit the two policy classes. Pick one. Consider splitting `Support/` into `Support/Integration/`, `Support/Normalization/`, `Support/Policy/`.

### 6. `EventAddressRegistry` + `EventAddressResolver` is a real pattern, but unclear

**Files**

- `src/Support/EventAddressRegistry.php`
- `src/Support/EventAddressResolver.php`

**Why this hurts friendliness**

The split between registry and resolver is unusual. It is unclear which one is the public entry point.

**Recommendation**

Document the pattern or consolidate. If a registry is needed, it should hold named resolvers and the resolver should look them up.

### 7. `EventModerationPolicy` may be redundant with `DefaultEventModerationWorkflow`

**Files**

- `src/Support/EventModerationPolicy.php`
- `src/Services/DefaultEventModerationWorkflow.php`

**Why this hurts friendliness**

Two classes that may both own moderation rules. If a moderation change needs to be made twice, the rules will drift.

**Recommendation**

Audit both. Pick the canonical owner. If both are needed, document their relationship.

## Concrete refactor plan

### Phase 1 — audit potential duplicates

**Steps**

1. Compare `EventContentSynchronizer` to `BackfillEventContentAction`.
2. Compare `EventLifecyclePolicy` to `LifecyclePolicy`.
3. Compare `EventModerationPolicy` to `DefaultEventModerationWorkflow`.
4. Pick the canonical owner for each pair.

### Phase 2 — contract-ize or split `RegistrationService`

**Steps**

1. Add `Contracts/RegistrationServiceInterface` or split into Actions.
2. Update callers.

### Phase 3 — organize `Support/`

**Steps**

1. Split into `Support/Integration/`, `Support/Normalization/`, `Support/Policy/`.
2. Update imports.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — audit potential duplicates

- [pending] Compare `EventContentSynchronizer` to `BackfillEventContentAction`.
- [pending] Compare `EventLifecyclePolicy` to `LifecyclePolicy`.
- [pending] Compare `EventModerationPolicy` to `DefaultEventModerationWorkflow`.
- [pending] Pick the canonical owner for each pair.

### Phase 2 — contract-ize or split `RegistrationService`

- [pending] Add `Contracts/RegistrationServiceInterface` or split into Actions.
- [pending] Update callers.

### Phase 3 — organize `Support/`

- [pending] Split into `Support/Integration/`, `Support/Normalization/`, `Support/Policy/`.
- [pending] Update imports.



## Suggested verification scope

- per-Action tests
- workflow contract tests
- resolver tests
- listener tests for order events

## Recommended first move

Phase 1 — audit potential duplicates. The package is the cleanest in the monorepo, but the suspected duplicates (`EventContentSynchronizer` vs `BackfillEventContentAction`, two policy classes) are the highest-leverage cleanup because they would otherwise drift over time.
