---
title: Checkout Package Friendliness Review
date: 2026-06-07
status: proposed
---

# `aiarmada/checkout` friendliness review

## Review brief

This review focused on two questions:

1. where the package already uses good extension seams, and where capability growth would still force hard-coded branching
2. where orchestration is repeated and should become a reusable Action, Service, or Use Case so the package stays friendly to multiple entrypoints

## What is already friendly

The package is not starting from zero. It already has several real seams:

- `CheckoutStepRegistryInterface` + `CheckoutStepInterface` give checkout a real step-pipeline seam.
- `PaymentGatewayResolverInterface` + `PaymentProcessorInterface` give payment gateways a real adapter seam.
- `SessionDataTransformerInterface` keeps billing and shipping normalization out of the main service.
- `ProcessCheckoutPaymentNotification` is already a useful shared ingress for webhook and CHIP event callbacks.
- The package docs explicitly describe step customization, replacement, and reordering.

The main friction is not “there are no seams.”
The friction is that some of the most variant-heavy areas still collapse back into central branching or duplicated orchestration.

## Findings summary

| Strength | Area | Finding |
| --- | --- | --- |
| Strong | Integration registration | `CheckoutServiceProvider` is still the central branching hub for optional integrations and payment gateways |
| Strong | Callback flow | Redirects, webhooks, and CHIP events still duplicate callback gating and session-processing orchestration |
| Strong | Step pipeline | Checkout step execution is duplicated across `processCheckout()` and `continueFromStep()` |
| Worth exploring | CHIP payment adapters | `ChipProcessor` and `CashierChipProcessor` duplicate CHIP-specific payload, status, and refund logic |
| Worth exploring | Step ordering policy | Step variants are partly modeled as provider-side conditionals instead of step metadata |

## Detailed findings

### 1. `CheckoutServiceProvider` is still the central branching hub

**Evidence**

- `src/CheckoutServiceProvider.php::registerPaymentProcessors()` hard-codes `cashier`, `cashier-chip`, and `chip`.
- `src/CheckoutServiceProvider.php::registerOptionalIntegrations()` hard-codes inventory, tax, discounts, and CHIP registration.
- Adding a new payment gateway or optional integration currently means editing the package service provider instead of shipping an adapter at an existing seam.
- The package already has a good partial example in `src/Support/ChipIntegrationRegistrar.php`, but that pattern is not generalized.

**Why this hurts friendliness**

The package advertises pluggable steps and multiple payment gateways, but the assembly path for those variants still lives in one central module.
That makes the interface shallower than it looks: callers can swap steps and processors, but package authors still need to edit the provider for every new variant.

**Better direction**

Introduce a registration seam instead of growing provider conditionals:

- a tagged `CheckoutIntegrationRegistrarInterface` for optional integrations
- a tagged `PaymentProcessorRegistrarInterface` or tagged `PaymentProcessorInterface` registration flow
- optional step contributors that register or disable steps without editing `CheckoutServiceProvider`

If the same registration pattern is likely to recur across packages, the generic tagging and registrar support belongs in `commerce-support`.

### 2. Payment callback orchestration is spread across multiple entrypoints

**Evidence**

- `src/Http/Controllers/PaymentCallbackController.php` resolves sessions, locks rows, short-circuits completed sessions, and decides whether failure or cancel callbacks should still run.
- `src/Actions/ProcessCheckoutPaymentNotification.php` also locks rows, short-circuits completed sessions, resolves callback type, and keeps its own callback-state gate in `canHandleCallback()`.
- `src/Support/HandleChipPurchaseEventForCheckout.php` adds another callback-type mapping layer before delegating.
- `src/Services/CheckoutService.php::handlePaymentCallback()` owns the actual mutation flow, so the entrypoints currently split responsibility instead of sharing one deeper module.
- The “allowed callback states” logic is duplicated as `shouldProcessPendingCallback()` in the controller and `canHandleCallback()` in the action.

**Why this hurts friendliness**

This is a classic multi-entrypoint orchestration smell.
The package already has three callback ingress paths:

- browser redirect
- generic webhook
- CHIP domain event

If another async source appears, it will almost certainly copy more of the same lock, dedupe, and callback-type rules.
That lowers locality and makes drift more likely.

**Better direction**

Create a single `HandleCheckoutPaymentCallback` use case that owns:

- session locking
- callback-type normalization
- completed-session short-circuiting
- allowed-state policy
- delegation into `CheckoutServiceInterface`

Then keep the current entrypoints as thin adapters:

- redirect controller = resolve session by token, then call the use case
- webhook processor = resolve payload, then call the use case
- CHIP event handler = resolve event metadata, then call the use case

This would deepen the seam instead of spreading checkout payment rules across HTTP, webhook, and event listeners.

### 3. Step pipeline execution is duplicated inside `CheckoutService`

**Evidence**

- `src/Services/CheckoutService.php::processCheckout()` loops over ordered steps, skips completed or skippable steps, calls `processStepInternal()`, and handles failure or payment redirect exits.
- `src/Services/CheckoutService.php::continueFromStep()` repeats the same orchestration pattern for the remainder of the pipeline.
- `src/Services/CheckoutService.php::retryPayment()` partially reconstructs the same payment-step restart flow before calling `continueFromStep()`.
- Completion responsibilities are also split: `CheckoutService` dispatches `CheckoutCompleted` after the pipeline, while `CreateOrderStep` also dispatches `CheckoutCompleted` for free orders.

**Why this hurts friendliness**

The package has a strong step seam, but the runner behind it is still duplicated.
Any future change to pipeline semantics—pause points, checkpointing, richer step policies, partial resume, analytics hooks, after-commit behavior—would need to be updated in more than one orchestration path.

**Better direction**

Extract a deeper pipeline runner, for example:

- `RunCheckoutPipeline`
- `ContinueCheckoutPipelineFromStep`
- or one runner with a start cursor and completion policy

That runner should own:

- step iteration
- skip rules
- redirect exits
- failure exits
- completion event dispatch

Then `CheckoutService` becomes the public interface, while the runner becomes the deep implementation module.

### 4. CHIP-specific payment logic is duplicated across processors

**Evidence**

- `src/Integrations/Payment/ChipProcessor.php` and `src/Integrations/Payment/CashierChipProcessor.php::createGuestPayment()` build almost the same CHIP purchase payload.
- Both classes duplicate CHIP status mapping in `handleCallback()`.
- Both classes duplicate CHIP status mapping again in `checkStatus()`.
- Both classes call CHIP refunds in nearly the same way.

**Why this hurts friendliness**

The package has a payment-processor seam, but CHIP behavior itself is still duplicated inside two adapters.
That makes CHIP rules harder to evolve and easier to drift.

**Better direction**

Introduce a shared CHIP support module that owns:

- purchase payload building
- status normalization
- refund wiring
- purchase lookup normalization

Then keep `ChipProcessor` and `CashierChipProcessor` thin.

If this CHIP normalization is useful outside checkout, the shared support should live in `commerce-support` or a CHIP-owned support namespace rather than staying package-local.

### 5. Step ordering variants are still partly provider-side conditionals

**Evidence**

- `CheckoutStepInterface` already exposes `getDependencies()`.
- `src/CheckoutServiceProvider.php::normalizeInventoryStepOrder()` manually moves `reserve_inventory` around `process_payment` based on config.
- `src/CheckoutServiceProvider.php::enforceStepDependencyOrder()` manually repairs `persist_customer` before `create_order`.
- The step seam exists, but some step-ordering knowledge still lives outside the steps themselves.

**Why this matters**

This is not the worst problem in the package today, but it is exactly the kind of place where future variants create more central branching.
If more phase-based steps appear—fraud screening, address verification, post-payment fulfillment checks, loyalty redemption—the provider will keep accumulating special order rules.

**Better direction**

Consider richer step metadata, such as:

- relative ordering constraints
- pre-payment vs post-payment phase metadata
- explicit “must run before / after” declarations beyond raw dependency names

That would keep ordering intelligence closer to the step seam instead of growing provider-side exception logic.

## Suggested order of attack

If this package is likely to gain more gateways or integrations soon, tackle the seams in this order:

1. extract shared callback handling into one use case
2. replace provider-side integration branching with tagged registrars or contributors
3. deepen the checkout pipeline runner
4. consolidate CHIP support logic behind a shared adapter
5. only then revisit richer step-order metadata if new step variants are arriving

## Concrete refactor plan

### Scope guardrails

Keep the first pass aggressively boring in the best way:

- no database schema changes
- no public route-name changes
- no config-key renames
- no contract breaks to `CheckoutServiceInterface`, `CheckoutStepInterface`, or `PaymentProcessorInterface`
- no behavior changes to callback token validation, owner scoping, or redirect semantics unless a failing characterization test proves the current behavior is unsafe

There is one especially important guardrail here: `CheckoutCompleted` is consumed outside this package by at least CHIP and Signals, so completion timing must be characterized before it is centralized.

### Target outcome

At the end of the refactor, checkout should have:

- one shared callback-handling use case for redirect, webhook, and CHIP event entrypoints
- one pipeline runner for full checkout and resume-from-step execution
- provider composition that follows the monorepo’s existing integration-registrar pattern instead of growing more central branching
- one shared CHIP support layer for payload and status normalization
- step-order policy isolated behind one module instead of being spread across provider helpers

### Tiny-commit sequence

#### Commit 1 — freeze the callback behavior with characterization tests

Add or extend tests to lock down the current callback behavior before moving code:

- extend `tests/src/Checkout/PaymentFlowTest.php` for parity between redirect success, failure, and cancel flows
- add a new focused test file for `ProcessCheckoutPaymentNotification` covering:
	- completed-session idempotency
	- gateway filtering via `expectedGateways`
	- allowed-state handling for success, failure, and cancel
	- missing-session and missing-reference behavior

This commit should not move production code yet.

#### Commit 2 — extract callback-state policy with no orchestration change

Create one small module, for example `CheckoutCallbackStatePolicy`, that answers:

- can this callback type run for this session state?
- should this callback be treated as idempotent because the session is already complete?

Use it from:

- `PaymentCallbackController`
- `ProcessCheckoutPaymentNotification`

That removes the duplicated `shouldProcessPendingCallback()` / `canHandleCallback()` logic without changing locking or session resolution yet.

#### Commit 3 — extract a single locked callback use case

Introduce a dedicated use case, for example `HandleCheckoutPaymentCallback`, that owns:

- locating the session for callback processing
- row locking
- completed-session short-circuiting
- invoking `CheckoutServiceInterface::handlePaymentCallback()`
- returning a small outcome object that both HTTP and non-HTTP entrypoints can understand

Then update:

- `PaymentCallbackController`
- `ProcessCheckoutWebhook`
- `HandleChipPurchaseEventForCheckout`
- `ProcessCheckoutPaymentNotification`

so they become thin adapters around that use case.

#### Commit 4 — keep callback payload normalization in one place

Move notification-specific callback-type resolution into its own support module instead of leaving it inside `ProcessCheckoutPaymentNotification`.

The goal is one place that converts inbound payload state into checkout callback intent.
This should stay separate from gateway-specific payment-status normalization so the later CHIP refactor can reuse only the CHIP-specific part.

#### Commit 5 — freeze pipeline behavior before extracting the runner

Add characterization tests for pipeline semantics, ideally in a new test file rather than overloading unrelated tests:

- full pipeline success
- redirect exit after `process_payment`
- retry-from-payment behavior
- skip behavior for already completed and skippable steps
- completion event dispatch count
- free-order flow

Reuse existing patterns in:

- `tests/src/Checkout/PaymentFlowTest.php`
- `tests/src/Checkout/CreateOrderStepTest.php`
- `tests/src/Checkout/CheckoutStepRegistryTest.php`

#### Commit 6 — extract `RunCheckoutPipeline`

Create a runner module that owns the shared loop currently duplicated between:

- `CheckoutService::processCheckout()`
- `CheckoutService::continueFromStep()`

The runner should accept:

- the session
- an optional starting step identifier or cursor
- a completion policy

and return the same `CheckoutResult` outcomes the public service already exposes.

At this point, keep `CheckoutService` public and thin; it should delegate rather than disappear.

#### Commit 7 — centralize checkout finalization

Create one finalization module, for example `FinalizeCheckoutSession`, that owns:

- final status transition to `Completed`
- dispatching `CheckoutCompleted`
- any “only once” guard around completion signaling

Then remove direct completion-event ownership from:

- the duplicated paths in `CheckoutService`
- the free-order branch in `CreateOrderStep`

This commit must verify downstream consumers, not just checkout tests. At minimum, rerun the package tests that touch CHIP and Signals listeners for `CheckoutCompleted`.

#### Commit 8 — align checkout with the monorepo’s integration-registrar pattern

Do not invent an entirely new registration pattern first.
Checkout should first follow the existing monorepo style already used in packages like Signals, Vouchers, JNT, and Cashier.

Extract registrar classes for checkout-owned integrations, starting with:

- CHIP listener registration
- payment processor registration
- optional step registration for inventory, tax, and discounts

The service provider should become a composition root that calls registrars, not the place where all integration logic accumulates.

#### Commit 9 — split payment processor registration from gateway resolution

Introduce one checkout-local registration seam for built-in payment processors.

Possible shape:

- `RegisterBuiltInPaymentProcessors`
- or a small registrar interface used only inside checkout for now

Refactor `CheckoutServiceProvider::registerPaymentProcessors()` so the provider no longer hard-codes processor assembly inline.

Keep this package-local first. If a second package needs the exact same registrar contract, then promote the shared abstraction into `commerce-support`.

#### Commit 10 — split optional step registration from provider boot logic

Extract the inventory, tax, and discount step assembly out of `CheckoutServiceProvider::registerOptionalIntegrations()`.

Each extracted module should own:

- install/availability checks
- config gating
- actual step registration or disablement

That gives checkout one seam per integration variant instead of one ever-growing `if` ladder.

#### Commit 11 — consolidate CHIP support behind shared checkout support classes

Add a shared CHIP support layer used by both `ChipProcessor` and `CashierChipProcessor`.

Recommended split:

- `ChipPurchasePayloadBuilder`
- `ChipPaymentStatusMapper`
- `ChipRefundGateway` or similar thin helper

Refactor duplicated logic out of:

- CHIP purchase payload creation
- callback status mapping
- status-check mapping
- refund wiring

The goal is not to merge the processors into one class. The goal is to keep each processor focused on its adapter differences while CHIP rules live in one place.

#### Commit 12 — isolate step-order policy

Extract provider helpers like:

- `resolveInventoryStepOrder()`
- `enforceStepDependencyOrder()`
- `ensureStepPrecedes()`

into a dedicated policy module, for example `CheckoutStepOrderPolicy`.

Do not add richer step metadata in the same commit.
First isolate the current ordering behavior. Only after that should the package decide whether phase metadata or richer relative-order constraints are actually needed.

#### Commit 13 — docs and cleanup

Update the checkout docs so the public extension story matches the refactor:

- `docs/05-checkout-steps.md`
- `docs/06-payment-gateways.md`
- `docs/08-integrations.md`

Document:

- how callback entrypoints are unified
- how integrations register themselves
- where custom payment processors should plug in
- which behaviors are still package-local versus candidates for `commerce-support`

### Verification plan

Use the existing test surface as the backbone instead of inventing a parallel verification story.

Primary checkout tests:

- `tests/src/Checkout/PaymentFlowTest.php`
- `tests/src/Checkout/CheckoutServiceProviderTest.php`
- `tests/src/Checkout/CheckoutStepRegistryTest.php`
- `tests/src/Checkout/CreateOrderStepTest.php`
- `tests/src/Checkout/CashierProcessorTest.php`

New checkout tests to add during the refactor:

- `ProcessCheckoutPaymentNotification` behavior tests
- pipeline runner behavior tests
- CHIP shared-support tests

Cross-package tests to run when centralizing completion behavior:

- CHIP listener coverage for checkout completion
- Signals listener coverage for checkout completion

### Explicitly out of scope for this pass

- redesigning the checkout domain model
- changing the public callback-token mechanism
- adding new payment gateways
- moving all registrar abstractions to `commerce-support` before a second real consumer exists
- redesigning `CheckoutStepInterface`
- rewriting step order into a brand-new dependency graph engine

## Bottom line

`aiarmada/checkout` already has the beginnings of a deep module shape.
The step registry, payment resolver, and callback action are good foundations.

The main friendliness problem is that the package still falls back to:

- a central service provider for variant assembly
- duplicated callback orchestration across entrypoints
- duplicated pipeline logic inside the main service
- duplicated CHIP normalization inside multiple payment adapters

That means the right move is not a rewrite.
The right move is to deepen the seams that already exist so new variants can arrive without editing central branching modules every time.


## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Sub-phase 1 — freeze the callback behavior with characterization tests

- [pending] extend `tests/src/Checkout/PaymentFlowTest.php` for parity between redirect success, failure, and cancel flows
- [pending] add a new focused test file for `ProcessCheckoutPaymentNotification` covering:

### Sub-phase 2 — extract callback-state policy with no orchestration change

- [pending] can this callback type run for this session state?
- [pending] should this callback be treated as idempotent because the session is already complete?
- [pending] `PaymentCallbackController`
- [pending] `ProcessCheckoutPaymentNotification`

### Sub-phase 3 — extract a single locked callback use case

- [pending] locating the session for callback processing
- [pending] row locking
- [pending] completed-session short-circuiting
- [pending] invoking `CheckoutServiceInterface::handlePaymentCallback()`
- [pending] returning a small outcome object that both HTTP and non-HTTP entrypoints can understand
- [pending] `PaymentCallbackController`
- [pending] `ProcessCheckoutWebhook`
- [pending] `HandleChipPurchaseEventForCheckout`
- [pending] `ProcessCheckoutPaymentNotification`

### Sub-phase 4 — keep callback payload normalization in one place

- [pending] Extract notification-specific callback-type resolution into its own support module
- [pending] Keep it separate from gateway-specific payment-status normalization for later CHIP refactor reuse

### Sub-phase 5 — freeze pipeline behavior before extracting the runner

- [pending] full pipeline success
- [pending] redirect exit after `process_payment`
- [pending] retry-from-payment behavior
- [pending] skip behavior for already completed and skippable steps
- [pending] completion event dispatch count
- [pending] free-order flow
- [pending] `tests/src/Checkout/PaymentFlowTest.php`
- [pending] `tests/src/Checkout/CreateOrderStepTest.php`
- [pending] `tests/src/Checkout/CheckoutStepRegistryTest.php`

### Sub-phase 6 — extract `RunCheckoutPipeline`

- [pending] `CheckoutService::processCheckout()`
- [pending] `CheckoutService::continueFromStep()`
- [pending] the session
- [pending] an optional starting step identifier or cursor
- [pending] a completion policy

### Sub-phase 7 — centralize checkout finalization

- [pending] final status transition to `Completed`
- [pending] dispatching `CheckoutCompleted`
- [pending] any “only once” guard around completion signaling
- [pending] the duplicated paths in `CheckoutService`
- [pending] the free-order branch in `CreateOrderStep`

### Sub-phase 8 — align checkout with the monorepo’s integration-registrar pattern

- [pending] CHIP listener registration
- [pending] payment processor registration
- [pending] optional step registration for inventory, tax, and discounts

### Sub-phase 9 — split payment processor registration from gateway resolution

- [pending] `RegisterBuiltInPaymentProcessors`
- [pending] or a small registrar interface used only inside checkout for now

### Sub-phase 10 — split optional step registration from provider boot logic

- [pending] install/availability checks
- [pending] config gating
- [pending] actual step registration or disablement

### Sub-phase 11 — consolidate CHIP support behind shared checkout support classes

- [pending] `ChipPurchasePayloadBuilder`
- [pending] `ChipPaymentStatusMapper`
- [pending] `ChipRefundGateway` or similar thin helper
- [pending] CHIP purchase payload creation
- [pending] callback status mapping
- [pending] status-check mapping
- [pending] refund wiring

### Sub-phase 12 — isolate step-order policy

- [pending] `resolveInventoryStepOrder()`
- [pending] `enforceStepDependencyOrder()`
- [pending] `ensureStepPrecedes()`

### Sub-phase 13 — docs and cleanup

- [pending] `docs/05-checkout-steps.md`
- [pending] `docs/06-payment-gateways.md`
- [pending] `docs/08-integrations.md`
- [pending] how callback entrypoints are unified
- [pending] how integrations register themselves
- [pending] where custom payment processors should plug in
- [pending] which behaviors are still package-local versus candidates for `commerce-support`
- [pending] `tests/src/Checkout/PaymentFlowTest.php`
- [pending] `tests/src/Checkout/CheckoutServiceProviderTest.php`
- [pending] `tests/src/Checkout/CheckoutStepRegistryTest.php`
- [pending] `tests/src/Checkout/CreateOrderStepTest.php`
- [pending] `tests/src/Checkout/CashierProcessorTest.php`
- [pending] `ProcessCheckoutPaymentNotification` behavior tests
- [pending] pipeline runner behavior tests
- [pending] CHIP shared-support tests
- [pending] CHIP listener coverage for checkout completion
- [pending] Signals listener coverage for checkout completion

