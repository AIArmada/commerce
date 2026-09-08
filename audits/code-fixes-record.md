# Code Fixes Record
Completed 2026-09-08. Historical record of implemented code fixes from the
package audits (the code-only Criticals track, the checkout currency
follow-ups, the three-stream parallel track, and the inventory A1
unification). Per-package audit files (`audits/*.md`) describe remaining
work only.

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
  payment-subject driver stays pending cashier track.
- **Deferred (honest):** physical index batches (persons partial/
  covering, org member/slug uniques, customers pivots, contacting
  partial-primary/timestamps) — app-level guards, locks, and
  transactional paths in place; dev-only rule now permits the batch
  as a follow-up. Orders/events addressing follow-ups open (events
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
- Suites: Feedback Area 50 passed (138 assertions),
  FilamentFeedback Area 8 passed (31 assertions); PHPStan level 6
  clean on both packages.
- **Implemented (2026-09-08, was held then dropped):** 9-file migration
  split (`2000_01_01_000001`–`000009`), schema-identical — mechanical
  per-table verification, only delta the replicated shared preamble.
  Dev-only delete-and-rerun; no backfill.
- **Deferred:** queued analytics recalc (no aggregate table exists).
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
  passed (65 assertions); PHPStan level 6 clean. No migration
  (NULL-unsafe uniques deferred).
- **Logged caller dependencies (main-agent follow-up, not this
  stream):** `CreateOrderStep` typed call + session id,
  `FulfillmentQueue` carrier/config logic, checkout document callers,
  inventory/promotions listener behavior.

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
