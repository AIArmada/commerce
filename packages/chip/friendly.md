# CHIP Architecture Audit

## Scope

This review looked at `packages/chip` with two heuristics:

- when a capability may grow variants, prefer a stable seam such as a contract, metadata table, hook, domain event, resolver, or support class
- when orchestration repeats, extract a reusable Action, Service, or Use Case so multiple entrypoints stay friendly to change

## Summary

The deepest refactor opportunity is the webhook slice. Today the webhook module has different implementations for live processing, retry, replay, and test simulation. That lowers locality and makes new CHIP event or status work spread across several files.

The second hotspot is optional integration work. The checkout customer bridge and docs integration already vary by installation, but their real interface is mostly hidden in hard-coded assumptions instead of explicit seams.

There is also no `packages/chip/tests` directory today, so the first refactor slice should lock current behavior down with package-local regression tests.

## Findings

### 1. Webhook ingest is not a single module interface

Strength: Strong

Files:

- `src/Webhooks/WebhookRouter.php:21-97`
- `src/Webhooks/ProcessChipWebhook.php:35-82`
- `src/Webhooks/WebhookRetryManager.php:61-104`
- `src/Testing/WebhookSimulator.php:453-470`
- `src/Actions/SyncChipRecordsFromApiAction.php:61-87`
- `src/ChipServiceProvider.php:241-249`

Problem:

`WebhookRouter` exposes `registerHandler()`, but it is not the real seam for runtime behavior. Live webhooks go through `ProcessChipWebhook` and `WebhookEventDispatcher`. Retries go through `WebhookRetryManager` and `WebhookRouter`. Test simulation dispatches events directly. API replay stores webhook data and links customers without using the same pipeline. The same `WebhookReceived::dispatch(...)` followed by typed dispatch is duplicated.

Why this hurts depth:

The current router is shallow. If it vanished, most of the complexity would stay spread across other callers. That means new webhook behavior has low leverage and poor locality.

Smallest refactor:

Introduce one container-resolved webhook ingest Action, for example `DispatchChipWebhookAction` or `ChipWebhookPipeline`, that owns enrichment, `WebhookReceived` emission, custom send-handler routing, typed event dispatch, and `WebhookResult`. Route live processing, retry, replay, and simulator dispatch through that one module.

### 2. Webhook event and status metadata is spread across several modules

Strength: Strong

Files:

- `src/Enums/WebhookEventType.php:14-188`
- `src/Services/WebhookEventDispatcher.php:56-197`
- `src/Gateways/ChipWebhookHandler.php:46-92`
- `src/Gateways/ChipWebhookHandler.php:138-172`
- `src/Gateways/ChipPaymentIntent.php:40-43`
- `src/Gateways/ChipPaymentIntent.php:149-169`
- `src/Events/WebhookReceived.php:258-279`
- `src/Data/EnrichedWebhookPayload.php:36-43`
- `src/Listeners/StoreWebhookData.php:58-60`
- `src/Listeners/StoreWebhookData.php:212-221`
- `src/Actions/SyncChipRecordsFromApiAction.php:71-77`

Problem:

The package already hints at a metadata seam with `WebhookEventType`, but callers do not use it as the source of truth. CHIP status to `PaymentStatus` translation is duplicated in `ChipWebhookHandler` and `ChipPaymentIntent`. Payment webhook purchase resolution is duplicated in `WebhookReceived`, `EnrichedWebhookPayload`, `StoreWebhookData`, and `ChipWebhookHandler`. Replay also synthesizes fallback `event_type` values separately.

Why this hurts depth:

Every new CHIP event, status, or payload-shape variant risks drift across gateway adapters, webhook dispatch, storage, and replay. Callers do not get leverage from a single definition module.

Smallest refactor:

Centralize this metadata in small support modules:

- `ChipPaymentStatusMapper`
- `ResolveWebhookPurchaseId`
- either enrich `WebhookEventType` further or add a `ChipWebhookDefinition` support class for fallback event names and typed dispatch metadata

These should start as package-local support classes. Only promote them to a public contract if a second adapter appears.

### 3. Owner resolution and embedded owner tuple handling has no stable seam

Strength: Strong

Files:

- `config/chip.php:70-75`
- `src/Support/ChipWebhookOwnerResolver.php:10-77`
- `src/Http/Controllers/WebhookController.php:28-45`
- `src/Webhooks/ProcessChipWebhook.php:87-97`
- `src/Webhooks/ProcessChipWebhook.php:200-237`
- `src/Data/EnrichedWebhookPayload.php:45-57`
- `src/Data/EnrichedWebhookPayload.php:94-104`
- `src/Webhooks/WebhookRetryManager.php:63-76`
- `src/Webhooks/WebhookRetryManager.php:149-164`
- `src/Listeners/GenerateDocOnPayment.php:53-85`
- `src/Listeners/GenerateDocOnRefund.php:58-90`
- `src/Testing/WebhookSimulator.php:540-559`

Problem:

Owner resolution is hard-coded to a static brand-id mapping resolver, while the embedded `__owner_type` and `__owner_id` protocol is stamped, parsed, and rehydrated in several different modules. Any new webhook entrypoint has to learn the same tuple rules again.

Why this hurts depth:

The owner-aware webhook module is shallow. Multi-tenant behavior is implemented repeatedly instead of sitting behind one seam, so locality is poor and alternative owner-resolution strategies require package edits.

Smallest refactor:

Introduce a real owner seam with:

- a container-resolved `ChipWebhookOwnerResolverInterface` whose default adapter keeps today's brand-id map behavior
- one support module that owns `embedOwner()`, `resolveEmbeddedOwner()`, and `runWithResolvedOwner()`

This is one of the few places where a contract is justified immediately because owner resolution already varies by installation strategy.

### 4. The checkout customer bridge exposes model classes, but not its real interface

Strength: Strong

Files:

- `config/chip.php:82-87`
- `src/ChipServiceProvider.php:191-216`
- `src/Listeners/LinkChipCustomerFromCheckoutCompletion.php:17-74`
- `src/Actions/LinkChipCustomerFromCheckout.php:23-114`
- `src/Commands/SyncChipRecordsFromApiCommand.php:32-65`

Problem:

The customer bridge only externalizes class names. Its real interface is still hard-coded: checkout completion is expected to expose `$event->session`, the session is expected to have `selected_payment_gateway`, `payment_id`, `customer_id`, and `payment_data.gateway_response.client_id`, and the local subject lookup is a direct customer model fetch.

Why this hurts depth:

The config suggests a deeper seam than the implementation really provides. Any checkout/session variant or custom customer subject forces edits in more than one caller.

Smallest refactor:

Extract one bridge module that owns session lookup, CHIP customer ID extraction, local subject lookup, and owner-context handling. A package-local support class is enough at first. If installations already vary here, promote it to a small adapter contract.

### 5. Docs integration mixes repeated orchestration with document-building decisions

Strength: Strong

Files:

- `src/Support/DocsIntegrationRegistrar.php:23-50`
- `src/ChipServiceProvider.php:170-173`
- `src/Listeners/GenerateDocOnPayment.php:25-224`
- `src/Listeners/GenerateDocOnRefund.php:25-229`

Problem:

Both queue listeners repeat the same orchestration: guard docs installation, resolve configured doc type, resolve owner, load the local purchase, dedupe existing docs, then create a doc. The document-building rules also live inside the listeners. On top of that, the registrar is instantiated directly with `new`, so even the registration module is not a replaceable seam.

Why this hurts depth:

The listeners are doing the work of a deeper module instead of acting as thin adapters. That lowers leverage for any future document variant such as receipts, debit notes, or installation-specific metadata rules.

Smallest refactor:

Keep listeners as event adapters only. Extract:

- one shared Action for the common prelude, for example `RunChipPurchaseDocGenerationAction`
- one support seam for document shaping, for example `BuildChipDocData`, with payment and refund adapters behind it

Resolve the registrar from the container instead of instantiating it manually.

### 6. Owner-batched console work is duplicated across commands

Strength: Worth exploring

Files:

- `src/Commands/RetryWebhooksCommand.php:25-78`
- `src/Commands/RetryWebhooksCommand.php:155-158`
- `src/Commands/CleanWebhooksCommand.php:25-48`
- `src/Commands/CleanWebhooksCommand.php:54-97`
- `src/Commands/CleanWebhooksCommand.php:144-147`

Problem:

Both commands enumerate distinct owners, rehydrate owner tuples, enter `OwnerContext`, run per-owner work, and aggregate results. This is the same owner-safe orchestration with different business callbacks.

Why this hurts depth:

Every future webhook maintenance command is likely to copy the same owner iteration logic. That reduces locality for non-HTTP owner-safe behavior.

Smallest refactor:

Extract a reusable owner-batch Action, for example `IterateWebhookOwnersAction` or `WebhookOwnerBatchRunner`, that accepts a callback and handles owner enumeration, explicit global mode, and total aggregation.

### 7. Send webhook handlers repeat the same update-and-dispatch flow

Strength: Worth exploring

Files:

- `src/Webhooks/Handlers/SendCompletedHandler.php:23-54`
- `src/Webhooks/Handlers/SendRejectedHandler.php:23-60`

Problem:

These handlers share the same interface and most of the same implementation: resolve send instruction ID, find the record without owner scope, skip if missing, update state, dispatch the payout event, return `WebhookResult`. Only the target state and event differ.

Why this hurts depth:

The current handlers are thin but duplicated. A new send webhook variant will likely copy another module instead of reusing one deeper Action.

Smallest refactor:

Extract `HandleSendInstructionWebhookAction` parameterized by target state and event dispatch. Keep each handler as a tiny adapter.

## Concrete Refactoring Plan

### Guardrail

Most new seams should start as package-local Actions or support classes, not public contracts. The immediate exceptions are owner resolution, and possibly the checkout bridge if installations already need different adapters. That keeps the refactor small while still improving locality.

### Phase 0. Add regression tests first

1. Create `packages/chip/tests` and cover the current webhook, owner, checkout bridge, docs, and send-handler flows before moving code.
2. Add tests for live webhook processing, retry, simulator dispatch, and API replay so the future webhook seam has one stable test surface.
3. Add cross-owner regression tests around embedded owner tuples and brand-id resolution.
4. Add tests for the checkout customer bridge from both checkout-completion and API-sync entrypoints.
5. Add tests for payment doc generation, refund doc generation, and send completed or rejected handlers.

Suggested verification targets:

- `./vendor/bin/pest --parallel packages/chip/tests`
- then narrower file or directory runs as each slice lands

### Phase 1. Remove pure duplication with small support modules

1. Add `ChipPaymentStatusMapper` and replace duplicated CHIP-to-`PaymentStatus` matches.
2. Add `ResolveWebhookPurchaseId` and replace repeated `related_to.type` and `related_to.id` parsing.
3. Add one owner-tuple support module and replace duplicated `__owner_type` and `__owner_id` parsing and embedding.
4. Keep behavior unchanged in this phase. The goal is higher locality before changing interfaces.

Success criteria:

- one module owns CHIP payment-status translation
- one module owns payment-webhook purchase resolution
- one module owns embedded owner tuple behavior

### Phase 2. Make webhook ingest a real seam

1. Introduce `DispatchChipWebhookAction` or `ChipWebhookPipeline`.
2. Move `WebhookReceived` emission and typed dispatch into that one module.
3. Move send-specific routing behind the same seam instead of giving retries a separate code path.
4. Make `ProcessChipWebhook`, `WebhookRetryManager`, `WebhookSimulator::dispatch()`, and API replay call the same module.
5. After all callers are migrated, either keep `WebhookRouter::registerHandler()` as the real extension seam or delete that public registration surface if the package does not actually want runtime registration.

Success criteria:

- live, retry, replay, and simulator dispatch share one ingest module
- new webhook behavior starts from one seam instead of several callers

### Phase 3. Deepen optional integration modules

1. Extract a dedicated checkout customer-bridge module and move session, payload, and subject assumptions into it.
2. Reuse that bridge from both checkout completion and API sync.
3. Extract one shared docs-generation Action for the common prelude.
4. Extract payment-doc and refund-doc builders behind a small document-shaping seam.
5. Resolve `DocsIntegrationRegistrar` from the container.

Success criteria:

- checkout completion and sync share the same bridge interface
- docs listeners become thin event adapters
- document-shaping rules live in one predictable place

### Phase 4. Deepen batch and send webhook modules

1. Add `WebhookOwnerBatchRunner` and use it from `RetryWebhooksCommand` and `CleanWebhooksCommand`.
2. Add `HandleSendInstructionWebhookAction` and use it from `SendCompletedHandler` and `SendRejectedHandler`.
3. Keep command output local. Move only the reusable owner-safe orchestration and send-update logic.

Success criteria:

- owner iteration exists once
- send webhook variants reuse one Action instead of copying handlers

## Suggested Commit Order

1. Add package-local regression tests.
2. Add `ChipPaymentStatusMapper`.
3. Add `ResolveWebhookPurchaseId`.
4. Add the owner-tuple support module.
5. Add unified webhook dispatch Action and migrate live processing.
6. Migrate retry, simulator, and replay onto the unified webhook seam.
7. Extract the checkout customer bridge.
8. Extract the docs-generation Action and document builders.
9. Extract the owner-batch runner.
10. Extract the send-instruction handler Action.

## Desired End State

- webhook processing has one real seam
- owner resolution and embedded owner tuple behavior have one source of truth
- optional integrations expose explicit interfaces instead of hidden assumptions
- repeated orchestration is concentrated in reusable Actions or support modules
- future CHIP event and integration variants can land with higher leverage and better locality


## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 0. — Add regression tests first

- [pending] Create `packages/chip/tests` and cover the current webhook, owner, checkout bridge, docs, and send-handler flows befo...
- [pending] Add tests for live webhook processing, retry, simulator dispatch, and API replay so the future webhook seam has one s...
- [pending] Add cross-owner regression tests around embedded owner tuples and brand-id resolution.
- [pending] Add tests for the checkout customer bridge from both checkout-completion and API-sync entrypoints.
- [pending] Add tests for payment doc generation, refund doc generation, and send completed or rejected handlers.

### Phase 1. — Remove pure duplication with small support modules

- [pending] Add `ChipPaymentStatusMapper` and replace duplicated CHIP-to-`PaymentStatus` matches.
- [pending] Add `ResolveWebhookPurchaseId` and replace repeated `related_to.type` and `related_to.id` parsing.
- [pending] Add one owner-tuple support module and replace duplicated `__owner_type` and `__owner_id` parsing and embedding.
- [pending] Keep behavior unchanged in this phase. The goal is higher locality before changing interfaces.

### Phase 2. — Make webhook ingest a real seam

- [pending] Introduce `DispatchChipWebhookAction` or `ChipWebhookPipeline`.
- [pending] Move `WebhookReceived` emission and typed dispatch into that one module.
- [pending] Move send-specific routing behind the same seam instead of giving retries a separate code path.
- [pending] Make `ProcessChipWebhook`, `WebhookRetryManager`, `WebhookSimulator::dispatch()`, and API replay call the same module.
- [pending] After all callers are migrated, either keep `WebhookRouter::registerHandler()` as the real extension seam or delete t...

### Phase 3. — Deepen optional integration modules

- [pending] Extract a dedicated checkout customer-bridge module and move session, payload, and subject assumptions into it.
- [pending] Reuse that bridge from both checkout completion and API sync.
- [pending] Extract one shared docs-generation Action for the common prelude.
- [pending] Extract payment-doc and refund-doc builders behind a small document-shaping seam.
- [pending] Resolve `DocsIntegrationRegistrar` from the container.

### Phase 4. — Deepen batch and send webhook modules

- [pending] Add `WebhookOwnerBatchRunner` and use it from `RetryWebhooksCommand` and `CleanWebhooksCommand`.
- [pending] Add `HandleSendInstructionWebhookAction` and use it from `SendCompletedHandler` and `SendRejectedHandler`.
- [pending] Keep command output local. Move only the reusable owner-safe orchestration and send-update logic.

