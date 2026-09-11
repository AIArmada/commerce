# Code Fixes Record
Living record of implemented code fixes from the package audits (the code-only Criticals track, the checkout currency
follow-ups, the parallel implementation tracks, per-package DONE conversions, and the inventory A1
unification). Per-package audit files (`audits/*.md`) carry verdicts plus residual notes; this file carries the
per-fix evidence chains.

Migrations live in [`migration-record.md`](migration-record.md) — including
one post-track addition (customers email unique, below). Review method for
everything here: independent re-verification against source (files, `rg`
repo-wide, call-chain tracing). Test/PHPStan/Pint counts are implementer-
reported and taken on trust; code correctness was verified directly.

## Orders — intake scoping (Critical, fixed)

- `CreateOrder::findExistingIntake()` now scopes with
  `forOwner(includeGlobal: config('orders.owner.include_global'))`.
- Both call paths route through it: happy path and `QueryException`
  duplicate-key fallback (verified `:37-44`, `:82-90`).
- Checkout already wrapped processing in `OwnerContext::withOwner()`
  (`CheckoutService::withSessionOwnerContext`) — the audit's call-site
  concern was not present.
- Tests: `tests/src/Orders/OrderIntakeTest.php` (9 passed per implementer).

## Checkout — amount + currency gates, backfill removal (Critical, fixed)

- Amount gate: `$paymentData['amount'] ?? grand_total` compared as `(int)`;
  mismatch records reconciliation metadata and returns `false` → step
  `failed()` → `PaymentConfirmed` never runs → `OrderPaid` (dispatched
  `afterCommit` in the transition) never fires. Chain traced end to end.
- Currency gate (commit `a60e3e2c6`): normalized (trim + uppercase) equality
  required; missing/unparseable rejected — no fallback. Mismatch taxonomy
  recorded (`amount_mismatch` / `currency_mismatch` / both) with expected +
  received values. Missing-amount session-total fallback intentionally
  retained (previously approved).
- Backfill removal (commit `df0fd32d2`): provider-result `currency` no longer
  backfilled from session in `ProcessPaymentStep`, `CheckoutService`, or the
  demo processor. Blast radius verified zero — every other currency consumer
  reads `$session->currency`; `PaymentResult::$currency` was already
  nullable by design.
- Residual (new, open): amounts are compared without currency-aware
  minor-unit handling — acceptable while all money is integer minor units
  in a single pipeline; revisit if multi-subunit currencies arrive.

## Cashier-chip — billing correctness (Critical, fixed)

- `period_key`: monthly keeps `Y-m`; sub-monthly uses ISO timestamps
  (`ClaimRenewalAttempt`). Real granularity fix, verified in source.
- Amount integrity: snapshotted at claim, mutation throws
  (`RenewalAttempt::updating` guard), bounded at claim
  (`Cashier::maximumAmount`) and at execution
  (`isAmountWithinBounds` in `RenewSubscriptionsCommand` and direct-charge
  paths).
- Fairness note: the "recomputed mutable totals" phrasing was half-stale
  (a snapshot column already existed) — but the added guards closed real
  gaps, so the fix is substantive, not cosmetic.

## Customers — resolver scoping + uniqueness (Critical, fixed)

- `findCustomerByEmail` scoped with `forOwner` + normalized email
  (`LOWER(TRIM)` semantics in SQL, `mb_*` in PHP — aligned).
- Model hooks (`creating`, email-dirty `updating`) enforce within-scope
  uniqueness with self-exclusion.
- Filament merge search was already owner-scoped (`OwnerUiScope`) — no
  change needed.
- Post-track migration (see `migration-record.md` appendix): uniqueness
  moved to canonical `contact_methods` after the native contact-layer
  removal (`2026_09_08_000002`, driver-aware partial/functional indexes,
  preflights); the old `customers`-table migration now only drops the
  legacy columns. Same-customer email retries are idempotent; no legacy
  columns were restored. App-level race closed by the constraint.

## Checkout — compensation (follow-up, implemented)

- `CheckoutStepInterface::compensate()` with safe no-op default for steps that
  own no reversible side effects.
- Executor compensates in reverse order over completed/processing/failed
  states; payment selects refund vs void by status; inventory release honors
  its kill-switch.
- Events registration and pass steps now persist the IDs they create before
  later work can fail, cancel/revoke those IDs during compensation, restore
  marked order-item options, and return an explicit compensation result.
- External delivery/email cannot be unsent; the created pass is nevertheless
  durably captured and revoked, leaving an auditable compensation trail.

## Chip — hardening (implemented)

- `spatie/laravel-webhook-client` declared AND vendored. Six phantom
  Filament resources deleted (zero references). Send-vs-Collect separation
  pinned.
- Idempotency mechanism is real (cache replay + payload fingerprint +
  lock-protected double-check in `PurchasesApi`; `PurchaseBuilder::
  idempotencyKey()`), and the fake-subclass signature incident was handled
  correctly (revert + alternate path).
- Checkout's direct and Cashier CHIP processors now use the persisted checkout
  session ID as the stable idempotency key; billable charges receive the same
  key through `PerformsCharges`. Setup purchases require an explicit key,
  intentionally making missing setup-attempt identity a breaking error.
- The unified Cashier CHIP gateway now rejects missing setup keys before
  billable dispatch. Filament's portal, customer page, and relation-manager
  callers retain one explicit key per Livewire setup attempt; resource actions
  reset it only after a URL is created, while the portal also caches the URL.
  Keyless setup rejection no longer creates a CHIP customer as a side effect.
- The official CHIP create-purchase documentation does not document a native
  idempotency header or field. The key is therefore consumed by the local
  cache/lock/fingerprint mechanism before the gateway request; no native
  gateway guarantee is claimed.
- **Setup-intent callers closed (follow-through):** `createSetupPurchase`
  and cashier's `ChipGateway::createSetupIntent` both reject a missing/
  blank `idempotency_key` before dispatch (fail-fast, documented message).
  Filament callers (portal page, relation manager, customer view) use a new
  `HasSetupPaymentMethodIdempotencyKey` trait — memoized UUID persisted as
  Livewire component state, so retries reuse the key; explicit reset starts
  a new attempt. Cashier + cashier-chip docs specify the contract with
  examples. External keyless callers must now supply and retain their own
  key (breaking, intentional — a silent fallback key would defeat
  idempotency).

## Inventory — A1 serial unification (implemented)

- Falsification first: vendor `State::getMorphClass()` returns the `$name`
  slug and the deleted enum carried byte-identical values — the audit's
  FQCN-corruption mechanism could not occur. Genuine defect was duplicated
  vocabulary only.
- Fix is behavior-preserving: badge/filter/form moved to state classes with
  identical string values; zero remaining enum references; docs updated.
- Full evidence chain in `migration-record.md`.

## Commerce-support foundation settling (implemented)

- **Money strictness:** `MoneyFormatter` (`formatMinor`, `formatMajor`,
  variants) and `MoneyNormalizer::toCents()` narrowed to `int`; every
  repo-wide caller passing `float|string` converted with explicit
  half-up rounding (centralized in a `ChipIntegerModel` helper where the
  pattern repeated). No shims or overloads retained. J&T float-math
  rerouting deferred — equivalence unproven, logged as follow-up.
- **Single navigation engine:** `CommerceNavigation` canonical;
  `ManageCommerceNavigation` reduced to a settings Page that consumes the
  engine for all discovery/resolution (verified call sites). Runtime
  overrides preserved with tests. Legitimate adapter layering, not a
  retained shim.
- **Authz models moved:** `Role`, `Permission`, `AuthzScope` now live under
  `AIArmada\Authz\Models`; originals deleted with zero remaining
  old-namespace references repo-wide (including the heavy filament
  consumer surface); `AuthzServiceProvider` owns registration. Stronger
  than the audit asked (no alias release cycle).
- **Octane lifecycle (follow-up, implemented):** `OwnerContext::flushState()`
  plus registry `flush()` methods, guarded `RequestReceived`/
  `RequestTerminated` listener registration (`class_exists` checks — safe
  without Octane installed), filament `Authz` binding scoped, leak
  regression tests. Registries verified to be unbound instance arrays, so
  no bindings were invented; flush is invoked only where bound.
- **Helpers/stubs (follow-up, implemented with correction):** shipping call
  sites moved to `MoneyFormatter::symbol()`; `commerce_morph_key()` helper
  created and both stubs shrunk to straight-line calls; helpers grouped by
  concern. Correction: `currency_symbol()` RETAINED, not deleted — the
  audit had the dependency backwards (`MoneyFormatter::symbol()` falls
  back TO the helper; delegating would recurse). Likewise the audit's
  "delegate to `ConditionalMigrationLoader`" was wrong-headed (it is purely
  a migration file loader) — grouping was the right fix.
- **Dependency-direction guard (new, permanent):**
  `tests/src/CommerceSupport/Architecture/
  CommerceSupportArchitectureTest.php` derives downstream namespaces from
  composer manifests and asserts `commerce-support/src` references none of
  them, plus a lean composer require. Keeps the foundation honest going
  forward.

## Identity cluster (implemented; audits converting to DONE)

- **Persons:** slug/searchable generation hook on saving, transactional
  scoped primary-name demotion, Filament search on `searchable_name`,
  Title fail-fast fallback. Topology decided and documented:
  `Person` = shared root, `Customer` = owner-scoped + `person_id`,
  `Organization` = tenant, `EventOrganizer` = event-scoped.
- **Customers pilot:** `Customer::person()` relation +
  `LinkCustomerToPerson` action (owner-safe, plus `executeByKey` for
  ID callers), `HasAddresses` adopted with `legacyAddresses()`
  retention and legacy bridge. **Dependency decision ratified:**
  customers hard-requires addressing (unconditional trait use;
  one-directional, no cycle) — recorded as policy for the orders pilot.
  Doctrine now mechanical: addressing CONTEXT declares canonical-addressing
  policy, and the architecture guard test pins addressing `require` +
  namespace independence.
- **Organizations/membership:** slug retry/backstop, single members-table
  source, invitation idempotence, AddMember race handling, middleware
  fallback reconciliation, Filament auth posture pinned.
- **Contacting:** `isDirty`-guarded display preservation, scoped locked
  primary demotion, enum validation, importer owner guards.
- **Addressing:** nested `database.tables` config rename, lineage
  normalizer, transactional guarded attach, `SingleAddressAreaSource`
  moved to core, adoption plan doc, customers pilot done.
  **Table-name split-brain resolved:** `AddressingTableResolver` is now the
  sole resolver for runtime readers AND all 19 migration files (17 shipped
  edited under explicit authorization + 2 track); zero flat-key reads
  repo-wide. Architecture guard pins addressing `require` (support +
  package-tools only) + consumer-namespace independence
  (`CommerceSupportArchitectureTest.php:94-136`, 4 passed). Resolver
  regression suite `AddressingTableResolverTest.php` green (3 passed).
- **Full stream (this pass):** fail-closed reference guards
  (`PersonsModelReferenceGuard`), `PersonStatus` enum + transition,
  pure formatted-name, bio/issuer invariants, gapless reorder,
  lazy resolver binding, `OrganizationStateTransition`, transfer
  actor/target assertions + audit hook, member revalidation, snapshot
  hardening (fail-closed + transactional + owner-mismatch rejection),
  channel-aware privacy defaults, exporter owner scope, full
  `ModelResolver`, canonical-only aliases, FK-authoritative
  normalization, provider validation, snapshot reason contract,
  segment tuple scoping, `CustomerProfileNormalizer`,
  `MergeCustomers` core action, `HasOwner`-only auto-assign,
  `Create/Update` optional `personId` linkage. Orders (287/664) +
  Checkout (253/931) canaries green.
- **Native contact-layer removal (backward-compat purge, dev-only
  migration edit):** `customers.email`, `customers.phone` dropped via
  the guarded cutover migration (no backfill); Customer model and all
  owned paths Contacting-only. Downstream reads in cashier/checkout/
  events degrade to null (null-safe) — logged dependencies for the
  owning tracks, not blockers. One stale checkout expectation rewritten
  to the Contacting-only doctrine.
- **Falsified, not implemented:** `ResolvesAddressingResources` merge
  (adapter-only seam, documented); contacting-to-`suggest` demotion
  (overruled by ratified hard-dep policy); non-addressing
  `lat`/`lng`/`google_place_id` hits (independent contracts in
  Signals/Events/legacy JSON).
- **Judgment calls (kept):** invitations accept unregistered emails;
  restore retains historical suspension/archive timestamps;
  payment-subject driver stays in customers (cashier track closed
  without relocating it; revisit only if the billing seam changes).
- **Deferred (honest):** physical index batches are now implemented
  (guarded `2026_09_11_*` migrations for persons/orgs/customers/
  contacting; see `migration-record.md`) — app-level guards, locks,
  and transactional paths remain as defense in depth. Orders/events
  addressing follow-ups open (events
  full-trait adoption; hardcoded prefixes already fixed — see
  `events.md` finding 3 re-check).

## Cashier multiplexer collapse (implemented)

- **A-1 fake unified records:** `UnifiedSubscription` is now a `final
  readonly` DTO (`packages/cashier/src/Support/UnifiedSubscription.php:15`);
  both fake record models (`UnifiedInvoiceRecord`,
  `UnifiedSubscriptionRecord`) deleted with the 8 flat exception files;
  Filament lists read through gateway clients
  (`ListSubscriptions.php:129`, `ListInvoices.php:106`). No tables were
  necessary or created — the package still ships no `database/` dir.
  Held migration proposal: none.
- **A-2 gateway truth:** `GatewayManager::supportedGateways()`
  (`GatewayManager.php:56`) is the single capability source with
  per-driver `class_exists` guards; Cashier and the detector delegate.
- **A-3 CHIP collapse:** `ChipGateway::client()` returns
  `CashierChip::chip()` (`ChipGateway.php:58`); billables/subscriptions
  resolve via `CashierChip::findBillable` / `::$subscriptionModel`;
  404s detected through typed CHIP exceptions. Thin local adapters
  remain only because the read-only `chip`/`cashier-chip` contracts do
  not implement cashier's unified contracts — every behavior path
  delegates (see deviations).
- **A-4 webhook:** real verify+handle on both gateways (CHIP
  `X-Signature` at `ChipGateway.php:391`, Stripe signature at
  `StripeGateway.php:394`); `SyncWebhook.php:29` dispatches
  `WebhookHandled` only when the result is handled; the empty
  `routes/web.php` endpoint is deleted.
- **A-5 unscoped read:** subscription lookup scoped through
  `OwnerScopedQuery` (`ChipGateway.php:189`) with cross-tenant
  regression coverage (`ChipGatewayOwnerScopeTest.php`).
- **A-6 Octane:** fresh gateway clients plus driver/facade flushing on
  `RequestReceived` (`CashierServiceProvider.php:121`).
- **C-1 100x bug:** `new Money($amount, new Currency($currency), false)`
  (`Cashier.php:169`) with golden `1000/MYR -> RM10.00` coverage.
- **C-2/C-3:** flat exception duplicates removed (canonical namespaced
  imports, e.g. `PaymentContract.php:7`); cart checkout reads
  checkout-owned inventory keys (`CartCheckoutBuilder.php:60`,
  `CartIntegrationRegistrar.php:150`).
- Suites: Cashier 255 passed (522 assertions), FilamentCashier 133
  passed (423 assertions); PHPStan level 6 clean on both source
  packages; `git diff --check` clean. Delegation tests mock the
  gateway contract and assert the seam, not chip internals.

## Authz hardening (implemented)

- **A2:** blanket `withoutGlobalScopes()` replaced with a documented
  `parent::getEloquentQuery()` (`PermissionResource.php:40` — Spatie
  permissions are global records; no scope to opt out of).
- **A3:** `Authz` discovery binding scoped per request with Octane
  flushing of `OwnerContext` and the model registries
  (`FilamentAuthzServiceProvider.php:33`, `:80`); facade accessor
  unchanged.
- **A4:** facade renamed `Authz` -> `FilamentAuthz`
  (`Facades/FilamentAuthz.php:22`); old file deleted; repository search
  confirms zero old-namespace imports outside audit metadata.
- **Q1:** generated record policies enforce
  `isRecordInCurrentOwnerScope()` before `$user->can()`
  (`GeneratePoliciesCommand.php:197`).
- **Hardening:** tenant checks use fail-closed `enforce && teams` in
  both `ScopesAuthzTenancy.php:12` and `ImpersonationScopeGuard.php:74`
  (deviation from the audit's literal `OR` — avoids team-pivot scoping
  when Spatie teams are disabled); boot-time separator assertion
  (exactly one non-alphanumeric char) plus scopes-require-teams guard
  (`AuthzServiceProvider.php:82`).
- Core coverage grown from 3 thin files to substantive behavior tests
  (`AuthorizationBehaviorTest.php:13`). Suites: Authz 13 passed (34
  assertions), FilamentAuthz 140 passed (251), FilamentAuthzScoped 12
  passed (32); PHPStan level 6 clean on both source packages.

## Feedback surveys (implemented, one held proposal)

- **A2 action collapse:** `SaveFeedbackFormStructureAction`
  (`packages/feedback/src/Actions/SaveFeedbackFormStructureAction.php:14`)
  is the single owner-guarded structure writer
  (`OwnerWriteGuard::findOrFailForOwner` on form + section with
  belongs-to-form check); six CRUD actions deleted; relation managers
  and template/duplicate flows rewired; enum-to-string visibility
  handling fixed in duplication.
- **A3 static cache:** deleted; `QuestionTypeRegistry::disabledTypes()`
  iterates `cases()` directly
  (`packages/feedback/src/Support/QuestionTypeRegistry.php:11`).
- **A4/A5 collision falsified + documented:** feedback-side names
  already namespaced; new `docs/05-boundaries.md` draws the
  registration/survey/social-signal lines. No edits in `engagement`
  or `events`.
- **Widgets:** all nine consume owner-keyed `FeedbackAnalyticsService`
  (e.g. `FeedbackNpsWidget.php:15`); dashboard composes widgets
  explicitly (`FeedbackDashboard.php:35`).
- **Security:** `bin2hex(random_bytes(32))` issuance
  (`SendFeedbackInvitationAction.php:37`), hashed + rate-limited
  lookup with expiry/cancelled/submitted guards and a lookup-only
  `withoutOwnerScope` window
  (`ResolveFeedbackInvitationTokenAction.php:21-55`); testimonial
  `scopePublished` gate (approved + published + visible,
  `FeedbackTestimonial.php:91`).
- Suites: Feedback Area 50 passed (138 assertions) at conversion,
  now 54 passed (152 assertions) with queued-analytics coverage.
  FilamentFeedback Area 8 passed (31 assertions); PHPStan level 6
  clean on both packages.
- **Implemented (2026-09-08, was held then dropped):** 9-file migration
  split (`2000_01_01_000001`–`000009`), schema-identical — mechanical
  per-table verification, only delta the replicated shared preamble.
  Dev-only delete-and-rerun; no backfill.
- **Queued analytics recalculation implemented** on the persisted
  aggregate table (provider-wired listener dispatches the job after
  commit).
- Corrected audit claims: "zero tests" was stale at implementation
  time; raw-`DB::table` scoring already applied `OwnerQuery`
  (`CalculateFeedbackResponseScoreAction.php:18`).

## Chip gateway (implemented, one held window)

- **A-2 single webhook writer:** configured `Webhook` subclass
  (`Webhook::class` as spatie `webhook_model`) is the `webhook_calls`
  system of record with owner scoping
  (`ChipServiceProvider.php:111`, `Models/Webhook.php:40`); vendor
  migration frozen; `WebhookLogger` + parallel writer deleted.
- **A-4/C-1 amount trust:** explicit currency, int-only quantities,
  per-component currency assertions, line-item subtotal and
  checkout-total reconciliation, response amount/currency validation
  (`PurchaseBuilder.php:196`, `PurchasesApi.php:374`).
- **A-5 canonical mapping:** `ChipPaymentStatusMapper::mapWebhook`
  (`ChipPaymentStatusMapper.php:52`) gives recognized events precedence;
  both CHIP consumers delegate.
- **A-6 sprawl removed:** checkout customer/document bridges,
  listeners, and support classes deleted; typed `WebhookReceived`
  (`:30`) / `PurchaseEvent` (`:27`) payload contract retained; generic
  `ChipCustomerDirectory` (contract-bound subjects, no checkout
  hardcoding) kept.
- **C-2 verified as-is:** `ChipCollectService` already fronts the
  `Services/Collect/*Api` facades (`:33`).
- Suites: Chip 1049 passed (2723 assertions, 4 skipped),
  FilamentChip 17 passed (73 assertions); PHPStan level 6 clean.
- **HELD (not dropped): crash recovery.** `PurchasesApi` posts the
  remote purchase before writing the idempotency cache — death between
  the lines re-posts on retry. Needs durable provider idempotency or
  a database outbox/ledger. First target for the money-path adversary.
- Dependencies logged (untouched): checkout-side amount assertions +
  event/status precedence; checkout/docs/customer subscribers for the
  new event contract.

## Cashier CHIP billing (implemented)

- **A-3 canonical collapse (owned side):** local status mapping +
  billing formatter deleted; `Payment` uses `PurchaseData` +
  `MoneyFormatter` (`Payment.php:71`); `findBillable()` retained
  (`Cashier.php:105`) for the read-only `cashier` caller.
- **A-4 explicit loading:** `$with` removed; `loadMissing` at call
  sites (`Subscription.php:232`, `ManagesSubscriptions.php:171`);
  owner-batched renewal queries (`RenewSubscriptionsCommand.php:64`).
- **A-5 loud vouchers:** `VoucherIntegration::assertAvailable`
  (`:22`) throws naming the missing package or the disabling flag,
  with config + usage docs.
- **A-6 narrowed scopes:** `OwnerBatchRunner` iteration; blanket
  `withoutGlobalScopes` gone.
- **C-1/C-2/C-3:** `CarbonImmutable` (`Coupon.php:11`,
  `Discount.php:8`); ordered UUIDs + create-then-prune
  (`Subscription.php:680`); renewal-attempt/CHIP-history-backed
  `latestPayment`/`upcomingInvoice`/`latestInvoice`/`invoices`
  (`Subscription.php:1005,1124,1188`).
- Suites: CashierChip 545 passed (908 assertions),
  FilamentCashierChip 93 passed (226 assertions); PHPStan level 6
  clean. No migration required. Delegation chain verified end to end
  (`ChipGateway:58` → `Cashier::chip:189` → `ChipCollectService:51`
  → canonical `*Api` facades).

## Money-path adversary fixes (implemented)

- **Crash recovery (chip):** durable `PurchaseIdempotencyLedger`
  (`chip/src/Support/PurchaseIdempotencyLedger.php:45`) reserves
  before the remote post and replays after; mutation legs keyed +
  locked + cached (`PurchasesApi.php:238`); keyless checkout purchases
  get deterministic payload-derived keys with explicit blanks still
  throwing (`PurchasesApi.php:655`).
- **Key forwarding (cashier-chip):** shared `IdempotencyKey` adapter
  (`Support/IdempotencyKey.php:15`) used by `ChargeChipCustomer`
  (`:58`); recurring-token path reaches the keyed seam
  (`PerformsCharges.php:96`).
- **Tenant isolation:** owner-blind billable lookup returns null
  (`Billing/Cashier.php:111`); invoice `client_id` exact-match
  (`ManagesInvoices.php:70`); gateway retrieval scoped on both
  gateways (`ChipGateway.php:214`, `StripeGateway.php:218`).
- **Callback evidence (checkout):** fail-closed amount/currency
  (`CashierProcessor.php:88`), evidence demotion with warning
  (`CheckoutService.php:385`), blocking reconciliation
  (`CreateOrderStep.php:318`), paid-wins policy
  (`CheckoutCallbackStatePolicy.php:19`).
- All 10 adversarial proofs green; suites green (Chip 1052/2730,
  CashierChip 549/919, Cashier 256/524, Checkout 253/931, filaments
  green); PHPStan L6 clean on touched packages.
- **Independent re-verification corrections:** the crash proof only
  exercised cache flush, not mid-flight termination — confirmed the
  pending-reservation path fails closed (reconciliation demanded, no
  replay, no double-charge); the evidence gate now requires amount
  evidence at the completion guard itself
  (`CheckoutService.php:427`), closing the prevalidated-Completed
  hole; explicit-blank keys throw at every entrypoint including raw
  `create()`; null-cache mutation bypass accepted as unreachable via
  the container. One more stale test rewritten (status-only callback
  now refused).
- **Stale tests updated to intended behavior** (8 total): directory mock
  owner arg, fail-closed amount case, amount evidence in 4 fixtures,
  paid-from-failed processing, status-only refusal.
- **Caveat (follow-up, not blocking):** payload-derived keys are only
  as unique as the payload — callers must keep passing explicit
  `reference` (session id); the derived path is a retry backstop, not
  a key substitute. Consider a warning log when deriving.

## Signals analytics (implemented)

- **A1 recorder split:** per-source `Recorders/*`
  (affiliate/network/cart/checkout/order/voucher) behind narrow
  shapes; `SignalRecorderSupport` throws on missing trusted fields;
  browser parsing lenient + documented; recorder 887→207 lines.
- **A2 single owner path:** `AutoAssignsSignalOwnerOnCreate`
  deleted; all 12 models `HasOwner` (isolation suite across models).
- **A3 endpoint hardening:** `throttle:signals-collect` + payload
  caps, field allowlists, per-property/IP limits on all four public
  routes; HMAC server-outcome path untouched as reference.
- **A4 listener map:** 20 listeners → explicit `SignalEventMap` +
  single `RecordCommerceSignal`.
- **A5 already delegated:** mutation guards on `OwnerWriteGuard` in
  baseline — verified, no change.
- **Q1 canonical condition:** `SignalCondition` owns operator
  matching + SQL compilation with fail-closed on bad match types.
- Suites: Signals 98 passed (748 assertions), FilamentSignals 26
  passed (80 assertions); PHPStan level 6 clean. No migration.
- Deferred: rollup reads, prod `EXPLAIN` review,
  `InteractionRuleService` extraction, clarity-only job rename.

## Growth experiments (implemented)

- **A2 thin delegator (kept deliberately):** 11 active call sites
  with mixed owner configs make removal unsafe; documented as the
  thin delegation point.
- **A3 action split:** pure builders + `MetricsCalculator`
  (no persistence/queries); actions orchestrate with signatures
  intact; adapter math deleted.
- **Q1 transitions:** `Experiment::transitionTo()` centralizes
  status→timestamp; archive + UI routed through it.
- **F1 adapters on core:** services/calculator called; no hardcoded
  currency; assignment-mutation gap falsified (no such actions).
- Suites: Growth 144 passed (452 assertions), FilamentGrowth 59
  passed (204 assertions); PHPStan level 6 clean. No migration.
- Deferred: dashboard batching (≤3-query test unclaimed).

## Checkout hardening (implemented)

- **A-3/A-4 owned ingress:** `checkout.webhooks.stripe.secret`
  (production fail-closed); per-gateway routes select verifiers,
  payload shape only validates.
- **A-5 canonical delegation:** mapper delegates to chip's canonical
  (`toCheckoutStatus` wrapper only); builder/refund verified as
  translation layers (payload arrays, session-id keys) and retained.
- **A-6 single precedence:** `payment.gateway_priority` config sole
  source; ctor defaults neutralized.
- **A-7 token discipline:** 24h TTL + single-use consume + 10/60 rate
  limit with timing-safe compare.
- **C-1/C-2:** atomic `DB::raw` increment under row guard + retry
  limit; `chk_`-prefixed references with gateway match and
  owner-context re-entry.
- **L-3/S-3:** production warning on null transformer; explicit
  global enforcement with message.
- **Falsified:** default event steps, unused hard requirements.
- **Deferred (correct):** voucher-cache invalidation (mutable
  provider ops need a contract outside checkout); cashier
  single-gateway split (cashier track).
- Suites: Checkout 263 passed (962 assertions); PHPStan level 6
  clean. Adversarial proofs unmodified and green.

## Engagement social graph (implemented + re-reviewed)

- **Typed contracts:** `CanInteract` actor bound + subject markers on
  all manager methods; `EngagementModelGuard::requireModel` /
  `requireContract` at boundaries (`EngagementModelGuard.php`,
  `EngagementEventEngagementManager stateFor`).
- **Trait delegates:** 14 public names kept over two internal
  helpers; parity-tested.
- **Bridge decided:** intentional separation (attendance intent vs
  social graph); phantom subscription-matching listener deleted with
  zero references remaining — fixed input for the events track.
- **Reminders + batching:** communications dispatch for delivery;
  both commands on `OwnerBatchRunner` (`chunkById(100)`).
- **Counters:** transactional writes, keyed reconciliation of stale
  per-type values, documented cadence.
- **Filament/security:** record re-resolution everywhere;
  owner-spoof + per-owner cache tests.
- **Re-review caught 4 live defects:** non-model `stateFor` inputs
  accepted; subscription/reminder mutations and delivery missing
  owner revalidation; string-vs-enum status comparisons defeating
  follow/bookmark/reaction idempotency (always-false `=== 'active'`
  on cast attributes); stale keyed counters never reset + CLI mapped
  to the wrong recalculators. All fixed with regressions
  (`ContractBoundaryTest`, `OwnerWriteBoundaryTest`,
  `CounterReconciliationTest`, duplicate-follow test).
- Suites: Engagement 47 passed (157 assertions),
  FilamentEngagement 5 passed (30 assertions); PHPStan level 6
  clean. No migration required.

## Affiliates (implemented)

- **State authority:** Spatie conversion/payout states expose `toEnum()`
  (`packages/affiliates/src/States/ConversionStatus.php:25-28`,
  `packages/affiliates/src/States/PayoutStatus.php:25-28`); unknown conversion,
  payout, and affiliate values throw (`packages/affiliates/src/States/ConversionStatus.php:105-125`,
  `packages/affiliates/src/States/PayoutStatus.php:105-125`,
  `packages/affiliates/src/States/AffiliateStatus.php:131-151`). Filament
  renders status through `fromString()` (`packages/filament-affiliates/src/Resources/AffiliateConversionResource/Tables/AffiliateConversionsTable.php:51-55,122-129`);
  the vouchers resolver has no lifecycle-status read (`packages/vouchers/src/Support/AffiliateReportingContextResolver.php:250-299`).
- **Cart layers:** `CartBridge` retains cookie hydration
  (`packages/affiliates/src/Support/Integrations/CartBridge.php:14-46`), and
  the provider binds it plus the live condition provider
  (`packages/affiliates/src/AffiliatesServiceProvider.php:105,118,124-143`).
  The four old affiliate decorator/trait/registrar paths are absent; scoped
  source/test grep found no remaining caller.
- **Voucher direction:** affiliates owns lookup and the voucher-applied
  listener (`packages/affiliates/src/AffiliatesServiceProvider.php:105,124-135`,
  `packages/affiliates/src/Support/Integrations/VoucherIntegrationRegistrar.php:11-28`).
  Vouchers' registrar remains for affiliate-created/activated vouchers
  (`packages/vouchers/src/Support/AffiliateIntegrationRegistrar.php:21-38,50-75`),
  with its ownership guard (`packages/vouchers/src/Support/VoucherAffiliateOwnershipGuard.php:16-27,72-89`).
- **Commission rules — explicit deferral:** commission matching remains in
  `CommissionRuleEngine`/`CommissionRuleType` (`packages/affiliates/src/Services/Commissions/CommissionRuleEngine.php:16-50`,
  `packages/affiliates/src/Enums/CommissionRuleType.php:7-17`); performance and
  fraud retain distinct contracts (`packages/affiliates/src/Contracts/PerformanceBonusRule.php:9-16`,
  `packages/affiliates/src/Contracts/FraudRule.php:12-18`). Future unification is
  an open breaking-design question; no generic rules framework was added.
- **Facade:** created for the lookup binding (`packages/affiliates/src/Facades/Affiliate.php:15-20`),
  matching the alias and docs (`packages/affiliates/composer.json:30-32`,
  `packages/affiliates/docs/04-usage.md:380-387`).
- **Owner dialects:** analytics now call `OwnerQuery::applyToQueryBuilder`
  (`packages/affiliates/src/Services/CohortAnalyzer.php:305-315,367-373,427-433`,
  `packages/affiliates/src/Services/PerformanceBonusService.php:156-178`); the
  three `ScopesBy*` concerns remain relational guards (`packages/affiliates/src/Models/Concerns/ScopesByAffiliateOwner.php:11-35`,
  `ScopesByProgramOwner.php:17-42`, `ScopesByTicketAffiliateOwner.php:11-36`).
- **Security/performance:** active, owner-scoped cookie lookup and forged/inactive
  rejection are verified (`packages/affiliates/src/Resolvers/DatabaseAffiliateLookup.php:58-75,95-133`,
  `packages/affiliates/src/Actions/Affiliates/AttachAffiliateFromCookie.php:21-40`,
  `tests/src/Affiliates/Unit/CartBridgeTest.php:52-90`). Payout logs contain
  identifiers/classes, not secrets (`packages/affiliates/src/Services/Payouts/StripeConnectProcessor.php:79-83,128-132,163-167`,
  `packages/affiliates/src/Services/Payouts/PayPalProcessor.php:100-104,140-144,199-200`).
  Aggregation is chunked and existing indexes were verified (`packages/affiliates/src/Services/DailyAggregationService.php:17-27`,
  `packages/affiliates/database/migrations/2000_01_01_000002_create_affiliate_attributions_table.php:57-63`,
  `packages/affiliates/database/migrations/2000_01_01_000003_create_affiliate_conversions_table.php:53-59`,
  `packages/affiliates/database/migrations/2000_01_01_000004_create_affiliate_payouts_table.php:34-36`). No speculative index migration was added; lazy widgets and the catalog seam remain (`vendor/filament/support/src/Concerns/CanBeLazy.php:7-16`,
  `packages/affiliates/src/Support/Catalog/PromotableRegistry.php:17-25`).
- **Canaries clarified:** searching `tests/src/Cart/**` and `tests/src/Events/**`
  confirmed the reported lines as `tests/src/Cart/Feature/Conditions/ConditionProviderRegistryTest.php:13-56`
  and `tests/src/Events/EventLifecycleWorkflowTest.php:13-45`. Exact reruns:
  `Tests:    1 passed (2 assertions)` / `Duration: 1.54s` /
  `Parallel: 8 processes`; and `Tests:    4 passed (4 assertions)` /
  `Duration: 3.73s` / `Parallel: 8 processes`. `BuyableTest` and
  `CrossTenantIsolationTest` were separate candidates.
- **Coverage record:** the DONE audit records Affiliates 1,139 passed, 5
  skipped, 2,611 assertions and FilamentAffiliates 317 passed (879 assertions)
  (`audits/affiliates.md:81-96`). This bookkeeping pass ran only the two
  canaries; no full suite or migration was run/required.

## Orders bridge + enforcement (implemented)

- **Typed cart bridge:** `CreateOrderFromCart::execute(Cart |
  CartManagerInterface, ...)` with explicit money mapping, nullable
  session id, owner-guarded customer; `OrderService::createFromCart`
  mirrored. No `CartContract` exists — typed against what's available.
  Duck-typed payloads fail; fake-cart mapping tests added.
- **DI + owner holes closed:** constructor injection;
  `AssertsOrderOwnerBoundary` on all mutations; 6/6 policies
  registered (`OrdersServiceProvider.php:46-51`); owner default
  aligned.
- **Doc pipeline:** `BuildsOrderPdf` deleted into `BuildsOrderDocs`;
  generators render-only.
- **Carriers:** bound-handler resolution with manual fallback;
  hardcoded J&T + `shipping.drivers.default` reads deleted
  (`availableCarriers()` itself remains a shipping-track item).
- **Delete safety:** transactional cascade, paid/final guard, force
  override, cancel/refund documented over delete.
- **Address/config:** canonical delegation with fallback + snapshot
  coverage; status shadow deleted; operator strings env-overridable
  (brand defaults retained — documented, not debranded).
- **Routes/widgets/listeners:** invoice throttle + sandbox/timeout
  audit; single OwnerCache aggregate (15s TTL); queued inventory
  bridges; conditional health registration.
- **Small items verified:** order-number retry, note-author check,
  item-status cast, per-order notification routing, admin-only
  internal notes.
- Suites: Orders 323 passed (725 assertions), FilamentOrders 22
  passed (65 assertions); PHPStan level 6 clean. Partial unique
  indexes implemented (`2026_09_11_000002`, guarded/idempotent).
- **Logged caller dependencies (applied post-conversion):**
  `CreateOrderStep` typed call + session id, `FulfillmentQueue`
  carrier/config logic, checkout document callers. Inventory/
  promotions listener behavior belongs to those tracks.

## Events ownership migration (implemented, one holding found + fixed)

- **7/46/11 split verified in source:** 7 direct `HasOwner`, 46
  `ScopesByEventOwner` relation seam, 11 intentional exceptions of 64
  models. Three scope classes deleted after parity; two "scopes" kept
  as verified value objects; submission scope retained as distinct.
- **Forks deleted** into canonical ticketing (quota constructor with
  guarded inventory check); zero `Events\Data` references remain.
- **Bridge:** comms-manager delivery with event context + reference
  attach (string-class refs, no hard dep).
- **Traits 24→9, helpers deleted**, eligibility/policy/queue/venue
  items closed per audit.
- **Holding finding (review-caught, fixed):** addressable guard lost
  its owner check with the boundary deletion (existence-only). Fixed:
  owner-tuple enforcement via `belongsToOwner()` + explicit-global
  handling, fail-closed; venue isolation test covers both directions.
- Suites: Events 244 passed (1105 assertions), FilamentEvents 18
  passed (181 assertions); Ticketing + Addressing canaries green.
  No migration. Out-of-set edits (4 ticketing files, 1 addressing
  guard) all verified necessary and listed in `events.md`.

## Affiliate network marketplace (implemented)

- **Trait unification:** `ScopesByBelongsToOwner` (relation path +
  config key) replaces both ~100-line traits; old traits deleted;
  3 model `use` lines + string-literal scope refs + filament call
  sites updated same pass (internal-only verified by grep).
- **Creative scoping:** `offer.site` chain; no migration needed per
  verified shape.
- **Boundary:** discovery vs execution documented; reader verified
  read-only; enrollment delegates; conversion precedence with
  duplicate guards; `orders` listener untouched.
- **Redirect/security:** explicit-global lookup + owner re-entry;
  signed + throttled route; random codes (64-bit — code comment
  corrected during integration); 1MB cap.
- **Catalog/perf/deps:** single `resolveField()`; owner-scoped
  dashboard; 30s `OwnerCache` widgets; filament requires core
  `affiliates`; navigation fallbacks removed.
- Suites: AffiliateNetwork 214 passed (442 assertions),
  FilamentAffiliateNetwork 53 passed (81 assertions); PHPStan
  level 6 clean. No migration (composite index deferred).
- Root `composer.lock` carries no path packages — dependency change
  introduces no lock staleness.

## Pricing resolution (implemented)

- **Tier engine:** canonical active-only `TierResolver`
  (price-list fallback ordering); divergent duplicate deleted after
  characterizing the divergence (old Action chose 800, Support chose
  inactive 700); matrix-tested.
- **Dead code:** `ResolveBasePrice`, `FormatPriceForDisplay`,
  `ResolveTierPrice`, `PricingIntegrationRegistrar` deleted
  (zero-callers verified); docs on direct primitives.
- **Promotions bridge:** `calculateDiscounts()` on the public
  contract; parity-tested; Checkout canary green with zero snapshot
  drift. Wall-clock evaluation stays until promotions exposes
  as-of behavior (their decision, logged).
- **Lifecycle:** deactivation enforced at model + calculator;
  owner-scoped transactional demotion with deterministic winner
  ordering; shared `effective_at` parser in all resolvers.
- **Validation:** tuple-scoped customer/segment checks with
  rejection tests; redundant override deleted (parity-proved);
  named currency exception; transactional deletes.
- **Simulator/adapter:** optional-products guard (both branches
  tested); promotions-owner widget default; docs match
  implementation; thin adapter confirmed.
- Suites: Pricing 145 passed (290 assertions), FilamentPricing 39
  passed (116 assertions); PHPStan level 6 clean. No migration
  (existing tier index covers).
- Test-only schema helper used instead of touching shared root
  `TestCase` — correct scoping.

## Cart multiplexer collapse (implemented + caller migration)

- **Snapshot collapse:** `Cart\Snapshots\*` owned by core (model,
  items, conditions, sync manager, event wiring); filament
  adapter-only; snapshot migrations moved core-side (recorded in
  `migration-record.md`); filament imports re-aliased.
- **Conditions/abandonment:** stored-condition core actions +
  thin shells; single hardened clear-abandoned command (owner
  confirmation + threshold guards).
- **Contracts:** `CartStorageInterface` deleted;
  `Target`/`ConditionPresets` builders replace `TargetPresets`;
  global `cart()` helper deleted (docs on facade/interface).
- **Money/identity:** minor-int prices with distinct money
  accessor; owner keys out of `$fillable`; canonical currency
  presenter; Octane flushing; centralized limits; env branding.
- **Caller migration (integration):** snapshot/manager/preset
  references rewired in vouchers (7 files), affiliates bridge
  availability gate, signals event strings, demo app/tests, root
  composer mappings, vouchers + events docs examples. Filament
  scoping test updated to canonical `cart.owner` key + context
  assignment.
- **Falsified:** `InMemoryStorage`, `ExampleRulesFactory`,
  "zero tests" — all live, retained.
- Suites: Cart 1052+2skipped/2731, FilamentCart 177/604; PHPStan
  L6 (123+33 files). Canaries: Orders 323, Checkout 266, Cashier
  256, Signals 98, Vouchers 889 green except 3 pre-existing
  remove/clear/replace failures proven unrelated via stash test
  (cart/voucher storage seam, logged for that track).

## Ticketing / seating / communications cluster (implemented)

- **Ticketing:** registry moved to core with all imports rewired;
  transactional batch issuance (`whereIn` collision retry,
  after-commit events); owner/config fixes; enum-aligned options;
  guarded Filament queries. Fork deletion required 4 ticketing
  files against a 1-file exception — expanded retroactively, all
  four verified necessary (quota constructor, PassData shape,
  owner-sniff retarget); reverting any of them re-breaks the High.
- **Seating:** set-based allocation (`limit()` fetch, batch holds,
  transaction kept); explicit GA handling; renderer verified
  single-shape; bulk-release regression.
- **Communications:** allowlist-gated auto-capture; five Nulls
  collapsed with bindings rewired; event-reference normalizer
  (multi-shape) closing the events-bridge dependency; webhook
  hardening; chunked deletes/prunes; payload redaction; fake
  coverage.
- **Hygiene note:** docs/index/evidence touch-ups shipped alongside
  the streams were accurate but outside every grant — accepted, not
  repeated. Next prompts should include hygiene explicitly or forbid
  it (playbook §4 gap).
- Suites: Ticketing 43/79, FilamentTicketing 8/20, Seating 62/116,
  FilamentSeating 8/9, Communications 265/1202,
  FilamentCommunications 41/67; canaries Events 244/1105, Orders
  323/725 green; PHPStan L6 clean. No migration required.

## Small-fry combo: csuite, references, moderation, docs (implemented)

- **csuite:** bundle policy documented (checkout-and-fulfillment +
  authorization); `aiarmada/authz` added to require; false guardrail
  rewritten as routing context; provider/plugin smoke test (1/83).
- **references:** standalone dependency declared; shared owner
  scoping adopted (deliberate direction change from global-by-design,
  recorded here — owner-scoped siblings make global rows the
  anomaly); transactional subtree deletion with explicit media
  cleanup + batched rows; canonical `reference_parts` JSON; loud
  slug-config failures.
- **moderation:** active/expired scopes + centralized transitions;
  owner-aware chunked expiry sweep + command; standard owner-config
  path; legacy validators + dead helpers deleted (zero callers).
  Root `TestCase.php` stale key intentionally left (shared infra).
- **docs:** typed `DocStatus` (persisted values contract-tested);
  `transitionStatusTo()` central (`transitionTo(Audit, bool)`
  collision documented); `OwnerWriteGuard` payment writes; lazy
  scoped numbering; row-locked sequences; shared money formatter;
  hardened tracking; delegating Filament actions.
- Suites: References 37/185, Moderation 61/256, Docs 176/469,
  FilamentDocs 63/247, smoke 1/83; PHPStan L6 clean. No migration
  required.

## Inventory stock (implemented)

- **Operations key:** wired where model + migration read it.
- **Single scoping:** direct predicates replace the redundant pair
  (relation kept for cross-location reads); equality + one-query
  proof (`InventoryOwnerScopeTest`).
- **Filament queries:** all six resources via parent; aggregator
  delegates to domain reports.
- **Catalog trait:** read-oriented; mutations through domain
  services.
- **Reconciliation:** durable report + reservation cleanup.
- **Costing:** named adapters injected; dead registries removed;
  enum/match allocation fully covered.
- **Ceremony/casts:** dead code deleted; hierarchy consolidated;
  explicit nulls-last, documented.
- Suites: Inventory 1152 passed + 6 skipped (2570 assertions),
  FilamentInventory 37 passed (136 assertions); PHPStan level 6
  clean. Checkout + Orders canaries green. No migration required.
- Deferred: movement composite index, concurrency stress tests,
  filament policy expansion — mitigations named in the audit.

## Fairness log

- Orders checkout-context concern: not present, dropped correctly.
- Cashier "mutable totals": half-stale, fix substantive (above).
- Filament customer search scoping: already correct, kept.
- "Zero tests" layout claims: repo-root suites existed; taken into account.
- Serial FQCN storage: false — the one audit mechanism overturned by
  vendor source in the entire review program.
- Direct CHIP checkout was partly overstated: `PurchasesApi` already fell back
  to the checkout `reference` when no explicit key was supplied. The explicit
  session key still closes the billable/setup adoption gap and makes the
  contract visible at each money-path caller.
- The Cashier CHIP billable integration also exposed a pre-existing wrapper
  accessor mismatch (`Payment::checkoutUrl()` / `Payment::id()` versus the
  nested purchase object); it was corrected because the new billable-path
  regression test exercised the real return path.

## Products / promotions / vouchers (implemented)

- **Products:** minor-unit money is canonical; the old money toggle and
  duplicate taxonomy were removed. Secure owner defaults, tuple-scoped
  identity, owner-safe queries/policies, capped and queued variant generation,
  extracted pricing/media helpers, live Filament config, and owner-aware stats
  are implemented. Slugs remain global while SKU identity is owner-scoped;
  identical retries resolve through `createOrFirst`, while genuine conflicts
  still reject.
- **Promotions:** per-customer limits, owner-checked order allocation reads,
  atomic usage increments, normalized exact code lookup, SQL prefilters, and
  chunked evaluation are implemented. The additive as-of API is parity-tested;
  default wall-clock pricing semantics remain unchanged. Duplicate issue-voucher
  actions are retained deliberately for their distinct list and record UX.
- **Vouchers:** the pre-existing remove/clear/replace cart failures were fixed
  in the owned voucher storage seam; domain money stays integer-only, float DTO
  inputs are rejected rather than coerced, lifecycle writes use transitions,
  deletion is transactional, `scopeLive` is canonical for redeemable rows,
  and `voucher_usage`/`times_used` plus `applied_count` are explicitly
  non-competitive reporting counters. The Filament stats aggregator delegates
  to domain definitions.
- **Recorded deferrals:** tuple-keyed partial uniques and related data cleanup
  remain deferred with no migration; the promotions wall-clock decision remains
  default-only for current callers; vouchers F1 Filament relocation is now
  implemented (three files moved to `filament-vouchers`, consumers rewired,
  zero old-namespace references — see the F1 closure entry below).
- Suites: Products **589 passed (1064 assertions)**, FilamentProducts **25
  passed (98 assertions)**; Promotions **73 passed (127 assertions)**,
  FilamentPromotions **37 passed (74 assertions)**; Vouchers **892 passed + 7
  skipped (1717 assertions)**, FilamentVouchers **42 passed (275 assertions)**.
- PHPStan level 6 is clean on all six PPV source trees after annotation-only
  fixes at `packages/filament-vouchers/src/Support/MoneyHelper.php:120-123`
  and `packages/filament-vouchers/src/Widgets/VoucherSuggestionsWidget.php:78-81`.
- No migration required. Full package-specific deviations and residuals are
  in `products.md`, `promotions.md`, and `vouchers.md`.

## Vouchers F1 relocation closure (implemented)

- The three domain `src/Filament/` files moved to `filament-vouchers`
  (`Exports/VoucherUsageExporter.php`, `Extensions/CartVoucherActions.php`,
  `Integrations/FilamentCartBridge.php`); the old directory is gone with no
  alias, re-export, or shim. Consumers rewired in the same pass:
  `filament-cart` `ViewCart.php`, `FilamentCart` `PagesTest.php`, the
  Filament Vouchers provider and usage table, plus owned README/docs
  imports. Repo-wide sweep confirms zero old-namespace references.
- Suites: FilamentCart 177 passed (604 assertions), FilamentVouchers 43
  passed (277 assertions), including new header-action coverage.

## Shipping / tax / J&T (implemented)

- **Shipping:** the Spatie shipment state machine is canonical; owner scope,
  request-scoped driver caching, one-driver-at-a-time rate shopping, runtime
  default-driver overrides, and chunked transactional child deletion are
  implemented (`packages/shipping/src/States/ShipmentStatus.php:16,68-96`,
  `packages/shipping/src/Services/RateShoppingEngine.php:173-232`,
  `packages/shipping/src/Models/Shipment.php:249-264`).
- **Tax:** calculator reads and writes carry the resolved owner through the
  shared owner-scope semantics; unknown exemption values are explicit, zone
  deletion is guarded at the application boundary, and the rate-rounding
  setting/formula are named and documented (`packages/tax/src/Services/TaxCalculator.php:105-159`,
  `packages/tax/src/Services/TaxOwnerScope.php:21-62`,
  `packages/tax/src/Models/TaxZone.php:253-325`).
- **J&T:** the canonical tracking-event vocabulary, integer minor-unit money
  path, single cart calculator, J&T-only webhook scope, queued retry/backoff,
  and policy-backed Filament authorization are implemented
  (`packages/jnt/src/Shipping/JntShippingDriver.php:366-398`,
  `packages/jnt/src/Webhooks/ProcessJntWebhook.php:27-40,229-235`,
  `packages/jnt/src/Models/JntWebhookLog.php:50-97`).
- **Recorded residuals and deferrals:** Shipping's P-2 cache-stampede proof
  and S-1 label token entropy/TTL and cache-eviction hardening; Tax L-2
  migration-convention, P-1 scale-dependent index, and P-2 event cardinality;
  J&T's stale demo key at `demo/config/jnt.php:81`, polling debounce, and
  EXPLAIN-gated tracking index question remain exactly as recorded in
  `shipping.md`, `tax.md`, and `jnt.md`.
- **Verification:** Shipping **530 passed, 1 skipped (1,328 assertions)**
  and FilamentShipping **103 passed (231 assertions)**; Tax **194 passed
  (442 assertions)** and FilamentTax **27 passed (71 assertions)**; Jnt
  **568 passed (1,729 assertions)** and FilamentJnt **34 passed (124
  assertions)** after the stale-test fix. The focused
  `SyncTrackingActionTest.php` run was **5 passed (9 assertions)**. The
  prior review record's Checkout and Orders canaries were green; no full
  suite was run in this closure.
- **Carried forward unchanged:** CommunicationDestination.address is
  intentionally still a scalar address column, not an encrypted:json cast.
  Source verification found that the resolver protects values before
  delivery persistence (`packages/communications/src/Services/CommunicationDestinationResolver.php:76-84`),
  while encrypting the source-of-truth address would require a type/length
  migration, key/backfill policy, and deployment gate. This remains an
  explicit follow-up decision, not an unverified claim of table-level
  encryption. Queued per-batch provider dispatch is explicitly deferred: the
  current command has no provider-send worker/action and its verified side
  effect is only scheduled-to-queued transition at
  `packages/communications/src/Console/Commands/DispatchDueCommunicationsCommand.php:83-91`.
  The hot-path composite index and chunking are implemented.
- **Shipped-migration position:** Communications added the verified hot-path
  indexes directly to the shipped dev migrations 000003, 000007, 000008, and
  000015. This remains an explicit shipped-migration deviation under the
  dev-only rule: delete and rerun local/dev databases; no production backfill
  is required. No foreign keys or cascades were added.
- No migration or compatibility shim is required for this cluster. The
  package-specific audit deviations and residual notes are in
  `shipping.md`, `tax.md`, and `jnt.md`; the demo configuration file and all
  read-only dependencies were left untouched.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.

## Deferral clearing pass — 2026-09-11 (three parallel streams)

This pass re-read the deferred entries and the residual notes on the 36 DONE
audits before changing code. The entries below are the disposition of every
item in the three delegated sets. Historical entries above remain in place;
none was silently dropped. All implementation work was authorized for the
owned sets under delegation-playbook §7.

### Stream A — physical index batches

- **Persons — implemented.** The guarded identity migration adds the
  re-derived slug, primary-name, primary-affiliation, and assignment lookup
  indexes at `packages/persons/database/migrations/2026_09_11_000001_add_identity_indexes_to_persons_tables.php:14-127`.
  Its dry-run duplicate preflight is `:267-307`; it reports samples and
  throws without deleting rows. The application sibling-demotion locks remain
  in `packages/persons/src/Models/PersonName.php:55-80` and
  `packages/persons/src/Models/Affiliation.php:66-91`.
- **Organizations — implemented.** The existing migration was strengthened
  in place, rather than creating a second migration:
  `packages/organizations/database/migrations/2026_09_07_074034_add_organization_integrity_indexes.php:13-81,180-218`.
  Slug and member-pair duplicate preflights now precede the unique indexes;
  the application retry/lock guards remain.
- **Customers — implemented.** Customer-first indexes for both pivots are
  guarded and idempotent at
  `packages/customers/database/migrations/2026_09_11_000002_add_customer_first_pivot_indexes.php:11-43`.
- **Contacting — implemented.** Partial/conditional primary uniqueness and
  primary/validity-window covering indexes are in
  `packages/contacting/database/migrations/2026_09_11_000003_add_primary_and_validity_indexes.php:14-134,246-303`.
  The existing transactional sibling guards remain in
  `packages/contacting/src/Models/ContactMethod.php:176-200,264-295` and
  `packages/contacting/src/Models/SocialProfile.php:191-215,274-305`.
- **Inventory — implemented.** The movement history index is
  `(from_location_id, to_location_id, occurred_at)` at
  `packages/inventory/database/migrations/2026_09_11_000004_add_movement_location_history_index.php:11-41`.
- **Migration safety — implemented.** New migrations guard table, required
  columns, and existing indexes; duplicate checks are dry-run preflights only.
  No backfill, delete, foreign key, or cascade was introduced. They are
  intended for local/dev delete-and-rerun. The application guards and database
  indexes were tested together, so neither layer was removed in favor of the
  other.
- **Verification.** Targeted migration tests passed: Persons 3/21,
  Organizations 6/12, Customers 1/4, Contacting 3/17, Inventory 4/18.
  Escalated owned areas also passed: Persons 31/119, FilamentPersons 3/7,
  Organizations 15/38, FilamentOrganizations 4/7, Customers 245/431,
  FilamentCustomers 28/63, Contacting 355/510, FilamentContacting 10 passed
  and 4 skipped/38, Inventory 1,153 passed and 6 skipped/2,574,
  FilamentInventory 37/136. The stream’s verdict is **closed**.

### Stream B — vouchers, promotions, cashier

- **Voucher cache invalidation — implemented.** The contract is documented
  at `packages/vouchers/src/Support/VoucherLookupCache.php:16-21`: owner-scoped
  positive lookups are cached, negative lookups are not, global-inclusive
  lookups bypass this cache, and out-of-band writers must explicitly invalidate.
  Lookup/invalidation wiring is at
  `packages/vouchers/src/Services/VoucherService.php:39-70`, with model
  save/code-change/delete hooks at `packages/vouchers/src/Models/Voucher.php:598-637`.
  The stale-then-invalidate proof is
  `tests/src/Vouchers/Unit/VoucherServiceTest.php:45-68`; owner separation and
  model-write invalidation are covered at `:70-212`.
- **Wall-clock promotions — implemented additively.** The optional as-of path
  is `packages/promotions/src/Services/PromotionService.php:33-46`; existing
  callers still use the unchanged wall-clock method. The parity test is
  `tests/src/Promotions/PromotionServiceBehaviorTest.php:28-42`.
- **Cashier single-gateway split — closed.** The configured-gateway
  seam is the single dispatch path
  (`packages/checkout/src/Integrations/Payment/CashierProcessor.php:55-57`;
  `GatewayManager::gateway(null)` resolves the configured gateway and the
  provider is read back for the result). Verified by seam tests plus the
  untouched adversarial proofs; see the "Closing streams" entry below.
- **Derived idempotency-key warning — implemented in Stream C’s authorized
  Checkout warning slice.** `packages/checkout/src/Support/ChipPurchasePayloadBuilder.php:13-20`
  logs the warning only on derivation and returns the same session key; the
  no-behavior-change proof is `tests/src/Orders/CheckoutDerivedKeyWarningTest.php:10-55`.
- **Verification.** Vouchers passed 898 with 7 skipped/1,733 assertions;
  FilamentVouchers 42/275; Promotions 73/127; FilamentPromotions 37/74;
  FilamentCashier 133/423. The ten untouched adversaries run individually
  were `tests/src/Cashier/AdversaryChipGatewayRetrievePaymentTest.php`,
  `tests/src/CashierChip/AdversaryChargeDropsIdempotencyKeyTest.php`,
  `tests/src/CashierChip/AdversaryFindBillableOwnerBlindTest.php`,
  `tests/src/CashierChip/AdversaryFindInvoiceCrossTenantTest.php`,
  `tests/src/CashierChip/AdversaryRecurringTokenDoubleChargeTest.php`,
  `tests/src/Checkout/AdversaryCashierCallbackAmountTest.php`,
  `tests/src/Checkout/AdversaryFailureThenPaidDroppedTest.php`,
  `tests/src/Chip/AdversaryCrashRecoveryDoublePostTest.php`,
  `tests/src/Chip/AdversaryKeylessCheckoutPurchaseTest.php`, and
  `tests/src/Chip/AdversaryRecurringChargeIdempotencyTest.php`; each passed.
  The B canaries passed: Cashier 256/524, Checkout 266/977, Pricing 145/290,
  Chip 1,052 with 4 skipped/2,730, and Cart 1,052 with 2 skipped/2,731.
  Stream B’s implemented items are closed, including the Cashier split
  (closed via the configured-gateway seam above).

### Stream C — residual sweep

- **Growth batching — implemented.** The batch action and typed row boundary
  are at `packages/growth/src/Actions/AggregateExperimentMetrics.php:89-229`
  and `packages/growth/src/Support/ExperimentMetricBatchRow.php:1-28`;
  dashboard aggregation consumes it at
  `packages/filament-growth/src/Support/GrowthStatsAggregator.php:17-70`.
  `tests/src/FilamentGrowth/Feature/ResultsAndDashboardTest.php:408-440`
  proves ten experiments in exactly three queries, with the expected counts.
- **Signals rollup reads — implemented; production EXPLAIN remains
  re-deferred with new measurements.** Dashboard summary/trend reads the
  daily rollup at `packages/signals/src/Services/SignalsDashboardService.php:30-53,71-100`.
  The regression fixture has 14 events, 2,500 minor-unit rollup revenue, and
  a raw 999,999-minor-unit event; it proves the raw event is not read at
  `tests/src/Signals/Unit/Services/SignalsDashboardServiceTest.php:318-382`.
  A production-scale EXPLAIN of the correlated acquisition subquery at
  `packages/signals/src/Services/AcquisitionReportService.php:156-176` could
  not be responsibly produced: the playbook environment has local SQLite,
  no production-like cardinality, and no live database. This is a measured
  environment/data blocker, not “still deferred” without reasoning.
- **Feedback queued recalculation — implemented.** The minimal guarded
  aggregate table is created at
  `packages/feedback/database/migrations/2026_09_11_000001_create_feedback_form_analytics_table.php:11-35`;
  the owner-scoped action/job and after-commit listener are wired at
  `packages/feedback/src/Actions/RecalculateFeedbackFormAnalyticsAction.php:19-74`,
  `packages/feedback/src/Jobs/RecalculateFeedbackFormAnalyticsJob.php:18-69`,
  and `packages/feedback/src/FeedbackServiceProvider.php:57-68`.
  Reads prefer the aggregate with a live fallback at
  `packages/feedback/src/Analytics/FeedbackAnalyticsService.php:28-38`.
  `tests/src/Feedback/FeedbackQueuedAnalyticsTest.php:20-146` covers stale
  recalculation, owner isolation, queue payload, immutability, and guarded
  rerun behavior.
- **Orders NULL-unsafe identities — implemented.** Guarded partial uniques
  and duplicate/partial-owner preflights are at
  `packages/orders/database/migrations/2026_09_11_000002_harden_order_identity_indexes.php:14-49,70-195`.
  The application payment check remains at
  `packages/orders/src/Models/OrderPayment.php:222-242`, while intake checks
  remain in the existing create action. Tests cover both layers at
  `tests/src/Orders/PaymentIdentityMigrationTest.php:19-31` and
  `tests/src/Orders/OrderPaymentTest.php:56-91`.
- **Carrier discovery — interface implemented; positive queue branch
  re-deferred with exact evidence.** The contract adds
  `availableCarriers()` at `packages/orders/src/Contracts/FulfillmentHandler.php:22-29`
  and Shipping implements it at
  `packages/shipping/src/Integrations/OrderFulfillmentHandler.php:31-61`.
  The fallback branch is tested at
  `tests/src/Orders/FulfillmentHandlerTest.php:12-36`.
  The positive Filament queue branch cannot execute because the read-only
  `packages/filament-shipping/src/Pages/FulfillmentQueue.php:318-327`
  checks an interface with `class_exists()` and returns before resolving the
  bound handler. That precise cross-set defect prevents the requested
  positive-branch proof without modifying an unowned file; the fallback
  guard remains covered.
- **Canonical test config — implemented.** `tests/src/TestCase.php:402`
  now uses `moderation.owner.enabled`.
- **J&T demo key — implemented.** `demo/config/jnt.php:81` now uses
  `region_multipliers_bp`, matching the guidance at
  `packages/jnt/docs/03-configuration.md:202-205`.
- **Events’ 11 exceptions — closed via machine-check test.**
  `tests/src/Events/OwnershipExceptionsMachineCheckTest.php` asserts
  exactly the 11 intentional catalog/pivot/submission files, so any
  silent addition or removal fails loudly. The Kennedy-list/grep-test
  choice is therefore settled, not re-deferred.
- **Downstream nullable contact reads — re-deferred with new static/data
  evidence.** Checkout already uses the canonical Contacting resolver at
  `packages/checkout/src/Steps/ProcessPaymentStep.php:391-423`. Other owning
  reads remain outside this slice, including Stripe customer email at
  `packages/cashier/src/Gateways/Stripe/StripeCustomer.php:43-46` and event
  recipient resolution at
  `packages/events/src/Resolvers/DefaultEventOrderItemFulfillmentResolver.php:47-123`.
  No production-like database or sample population is available in the
  playbook environment, so an actual null rate cannot be measured honestly;
  no sweeping compatibility change was made. The follow-up must run the
  three owning reads against representative data before choosing adoption.
- **Pending-ledger exercise — implemented.** The end-to-end reserve → crash
  observation → reconciliation-required failure → record/resolve → successful
  find sequence is at `tests/src/Orders/PendingLedgerExerciseTest.php:10-55`.
- **Verification.** Growth 144/452 and FilamentGrowth 60/209; Signals 99/755
  and FilamentSignals 26/80; Feedback 54/152 and FilamentFeedback 8/31;
  Orders 330/743; Shipping (read-only area check) 530 with 1 skipped/1,328.
  The focused Growth query proof was rerun after the typed-row correction and
  passed 22/67. Stream C is closed except for the explicitly re-deferred
  Signals EXPLAIN, positive carrier-queue branch, Events markers, and
  downstream-null-rate items above.

### Audit deviations

- Organizations reused and hardened its existing shipped migration; no
  duplicate migration was created.
- The Persons audit’s proposed title uniqueness was re-derived as stale:
  the existing title uniqueness premise is already present, so the new batch
  adds the target/status lookup index instead of duplicating that constraint.
- Contacting’s live validity fields are `valid_from`/`valid_until`, not
  `created_at`; Inventory’s movement chronology is `occurred_at`, not
  `created_at`. The indexes follow the current schemas.
- PostgreSQL/SQLite use true partial indexes. MySQL uses the supported
  conditional functional/CASE form (and its native nullable-unique behavior
  where applicable). All new migrations are guarded and rerunnable, with
  no backfills, deletes, foreign keys, or cascades; local/dev delete-and-rerun
  is the migration procedure.
- The first FilamentPromotions parallel run lost a worker result under
  resource pressure; the bounded four-process retry passed 37/74. No test
  assertion failed.
- No full repository suite was run. The exact targeted files, owned-area
  escalations, untouched adversary proofs, and main Chip/Cashier/Checkout
  canaries are the verification boundary for this pass.

## Four-stream deferral clearance — 2026-09-11 (no-legacy clarification)

All four streams re-read `docs/agents/delegation-playbook.md`, the relevant
deferral entries, and the residual notes from the DONE audits before acting.
The later user clarification that backward compatibility and legacy behavior
are unwanted controls the earlier Orders pilot wording. No legacy alias,
dual-read/write path, or compatibility shim was added.

### Stream 1 — Orders HasAddresses pilot

- **VERDICT: IMPLEMENTED (write path + consumers + invoice read path;
  pilot complete).** `Order` uses `HasAddresses`; creation attaches
  canonical addresses via `attachAddress()` (`Order.php:77`,
  `CreateOrder.php:325`); legacy `billingAddress`/`shippingAddress`
  relations deleted with no alias, shim, or dual-write. Checkout +
  fulfillment consumers migrated. Digital orders stay addressless
  through persistence, snapshot, and invoice rendering
  (`OrderAddresslessInvoiceTest`).
- **Invoice read path closed (follow-up micro-stream):** builders read
  canonical billing→shipping fallback with addressless omit
  (`BuildsOrderDocs.php:160`); Blade + infolist render canonically;
  hard `aiarmada/addressing` require added
  (`packages/orders/composer.json:21`). Zero legacy references remain
  across all five files. The 12 PHPStan errors are gone.
  Addressful + removal + digital coverage extended in the same test
  file.
- Final area verification: `./vendor/bin/pest --parallel tests/src/Orders`
  — 334 passed, 785 assertions; Checkout 266/977; FilamentOrders 22/65;
  PHPStan `packages/orders/src` clean.

### Stream 2 — Event notification table retirement

- **VERDICT: IMPLEMENTED ( expanded grant).** Change notices dispatch
  through the comms bridge (`DispatchEventChangeChainAction:95`,
  listener, dispatcher with `CommunicationManager` contract);
  notification models, job, event wrapper, factories, relations,
  provider wiring, config keys, and the Filament center deleted;
  guarded drop migration added
  (`2026_09_12_000001_drop_event_notification_tables.php`,
  `hasTable` preflights); grep-test proves zero references
  (`EventNotificationDispatchTest:115`).
- Emptiness re-proven fresh on `cdemo` (0/0 rows, quoted in report);
  `commerce_demo` from `.env` does not exist — proof limited to the
  available persistent database, stated plainly.
- **Follow-up closed:** `aiarmada/communications: self.version` added
  to `packages/events/composer.json:24` (hard require, canonical
  doctrine); resolution test proves the dispatcher contract resolves
  (`EventNotificationDispatcherResolutionTest`, 1/2).
- Suites: Events 243/1084, FilamentEvents 18/175; comms
  event-reference 4/14 and addressless invoice canaries green.

### Stream 3 — Money decisions

- **Voucher cache: VERIFIED/CLOSED.** The contract is documented at
  `packages/vouchers/src/Support/VoucherLookupCache.php:16-21`, wired by
  `packages/vouchers/src/Services/VoucherService.php:34-70`, invalidated by
  `packages/vouchers/src/Models/Voucher.php:598,634-637`, and proved by the
  stale-read test at `tests/src/Vouchers/Unit/VoucherServiceTest.php:45`.
- **Wall-clock promotions: VERIFIED/CLOSED.** The optional as-of path is at
  `packages/promotions/src/Services/PromotionService.php:28-46`; the default
  path remains unchanged at `:91-102`. Parity is proved at
  `tests/src/Promotions/PromotionServiceBehaviorTest.php:28-42`, with the
  supplied-instant proof at `:45-68`.
- **Cashier gateway split: IMPLEMENTED.** The named Checkout bridge now
  resolves the configured gateway seam with `gateway(null)` and no longer
  selects a gateway from `PaymentRequest::provider`
  (`packages/checkout/src/Integrations/Payment/CashierProcessor.php:55-57`).
  `tests/src/Checkout/CashierProcessorTest.php:65-128` asserts the seam
  directly. No `packages/cashier/src/Gateways/**` edit was necessary; the
  existing configured seam remains at `packages/cashier/src/GatewayManager.php:46-48`.
- Stream B verification: targeted CashierProcessor 5 passed/51 assertions;
  Cashier 256/524; Checkout 266/977; PHPStan on `packages/checkout/src`
  clean; Pint and `git diff --check` clean.
- The ten untouched adversary proofs also ran individually and all passed:
  `tests/src/Cashier/AdversaryChipGatewayRetrievePaymentTest.php`,
  `tests/src/CashierChip/AdversaryChargeDropsIdempotencyKeyTest.php`,
  `tests/src/CashierChip/AdversaryFindInvoiceCrossTenantTest.php`,
  `tests/src/CashierChip/AdversaryRecurringTokenDoubleChargeTest.php`,
  `tests/src/CashierChip/AdversaryFindBillableOwnerBlindTest.php`,
  `tests/src/Chip/AdversaryRecurringChargeIdempotencyTest.php`,
  `tests/src/Chip/AdversaryCrashRecoveryDoublePostTest.php`,
  `tests/src/Chip/AdversaryKeylessCheckoutPurchaseTest.php`,
  `tests/src/Checkout/AdversaryCashierCallbackAmountTest.php`, and
  `tests/src/Checkout/AdversaryFailureThenPaidDroppedTest.php`.

### Stream 4 — Residual sweep

- **Growth batching: CLOSED.** The existing batching path is at
  `packages/growth/src/Actions/AggregateExperimentMetrics.php:89-112,229-325`;
  `tests/src/FilamentGrowth/Feature/ResultsAndDashboardTest.php:408-440`
  measured exactly 3 queries for 10 active experiments, 22 variants, and 22
  assignments, satisfying the gate. No Growth source was changed.
- **Ownership exceptions: CLOSED.** The only source/test addition is
  `tests/src/Events/OwnershipExceptionsMachineCheckTest.php:7-56`, which
  greps the source and asserts exactly 11 named exceptions.
- TestCase/demo-key/ManageNav items were re-verified at
  `tests/src/TestCase.php:401-402`, `demo/config/jnt.php:81-85`, and
  `tests/src/FilamentCommerceSupport/FilamentCommerceSupportTest.php:23-30`.
  Octane remains blocked because `laravel/octane` is not installed. Signals
  EXPLAIN review remains evidence-only at
  `packages/signals/src/Services/AcquisitionReportService.php:149-176`.
  The production null-rate measurement was `0/0` because no production-like
  database/data is available; no sweeping contacting-read change was made.
- Exact focused runs: ResultsAndDashboard 1/5; ownership machine check 1/2;
  SignalsDashboardService 1/7; AcquisitionReportService 1/17; event contact
  read 1/1; ManageNav 9/20. Pint and PHP lint passed.

### Audit deviations

- The later no-legacy clarification superseded the earlier Orders instruction
  to keep the frozen table readable; Stream 1 implemented full replacement
  under it, and the follow-up closed the invoice read path plus composer
  require (see Stream 1 verdict above).
- Stream 2's persistent `cdemo` proof is explicit `0/0`; the configured
  `commerce_demo` database was absent, and the remaining live references are
  outside the authorized set. No migration-record update was possible without
  changing the out-of-scope `audits/migration-record.md`.
- Stream 3 used the explicitly granted Checkout exception. Provider-aware
  refund/void/status paths remain at
  `packages/checkout/src/Integrations/Payment/CashierProcessor.php:130,182,235`
  and the unused helper remains at `:387-397`; those are outside the named
  bridge slice and were not changed.
- Main integration canaries ran with `--parallel`: Events — 245 passed,
  1,107 assertions; Cashier — 256 passed, 524 assertions; Checkout — 266
  passed, 977 assertions. The ten adversary proofs passed individually (10
  tests, 23 assertions). No full repository suite was run.
