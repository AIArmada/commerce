---
title: Open Issues and Follow-ups
status: active
surface: repo-root
family: maintenance
---

# Open Issues and Follow-ups

Findings from the August 2026 audit of the `packages/*` Artisan command surface, plus
adjacent bugs that surfaced while verifying the deletions. Nothing here is fixed.

The 18 duplicate / decorative commands identified by that audit have been removed. See
[Cleanup summary](#cleanup-summary) at the bottom for what was deleted and what replaced it.

Items 22-41 come from a separate documentation-correctness sweep across all 69 packages
(373 doc files). That sweep fixed roughly 700 doc defects; the entries here are the code
defects it surfaced but deliberately did not touch.

**How to read the severities**

| Severity | Meaning |
|---|---|
| `CRITICAL` | Silent data loss or a security hole in production. Fix before next deploy. |
| `HIGH` | A documented contract is unenforced, or revenue-affecting work never runs. |
| `MEDIUM` | Real defect, bounded blast radius, or dead code that misleads future readers. |
| `LOW` | Hygiene. Safe to defer. |

---

## CRITICAL

### 1. `Prohibitable` never fires — `authz:super-admin` is unguarded in production

**Package:** `authz`, `filament-authz`

`CommandProhibitor::prohibitDestructiveCommands()` is the only thing that ever sets the
static flag, and it has **zero call sites** outside its own definition:

```
rg -l "prohibitDestructiveCommands" packages/*/src packages/*/config bootstrap
  -> (nothing)
```

The docs describe it as app-opt-in (`filament-authz/docs/99-troubleshooting.md:174`), so this
may be deliberate. But four commands check the flag and believe they are guarded:

- `packages/authz/src/Console/Commands/SuperAdminCommand.php:46`
- `packages/authz/src/Console/Commands/SyncAuthzCommand.php:25`
- `packages/filament-authz/src/Console/GeneratePoliciesCommand.php:48`
- `packages/filament-authz/src/Console/SeederCommand.php:49`

`initializeProhibitable()` therefore always returns `true`, and `authz:super-admin` will
create a user and assign a super-admin role in production unless the host app wires the
prohibition itself.

Secondary problem: `Prohibitable` holds `protected static bool $prohibited`, which is
request-leaking static mutable state. The repo's own Octane rule
(`.ai/rules/00-overview`) flags this. `CommandProhibitor::$commands` and `::$prohibited` are
also static. `reset()` exists but has no callers outside tests.

**Next action:** decide whether the guard is opt-in or mandatory. If mandatory, call
`prohibitDestructiveCommands()` from a service provider and replace the static flag with
container-scoped state. If genuinely opt-in, add an explicit `app()->isProduction()` guard
to `SuperAdminCommand` and drop the four `initializeProhibitable()` calls that imply
protection that does not exist.

### 2. Host app destroys digest batches every minute

**Where:** `~/Herd/ilmu360/app/Console/Commands/SendDigestNotificationsCommand.php`
(registered in `~/Herd/ilmu360/routes/console.php` on `everyMinute()`)

`communications:send-digests` is an **app-level** command that squats the package namespace.
It matches no digest deliveries, then:

```php
$batch->update(['started_at' => CarbonImmutable::now()]);
// ponytail: process batch deliveries in Phase 2
$batch->update([
    'completed_at' => CarbonImmutable::now(),
    'started_at'   => CarbonImmutable::now(),
]);
```

Every due `CommunicationBatch` is permanently marked `completed_at` with zero sends. The
selection query filters `whereNull('completed_at')`, so those batches are never retried.

The package's real implementation — `communications:dispatch-due` — is fully implemented,
owner-scoped, `chunkById`-batched, and dispatches `DispatchCommunicationDeliveriesJob` per
communication. It is never called.

**Next action:** delete the app stub, schedule `communications:dispatch-due`. Note this only
matters for apps that adopt the package; the stub is not in this repo.

---

## HIGH

### 3. 13 cron-equivalent commands are never scheduled

**Only 2 of the 58 remaining commands are on a scheduler:**

```
rg -n "schedule->command" packages/*/src/*ServiceProvider.php
  packages/seating/src/SeatingServiceProvider.php:41     ReleaseExpiredHoldsCommand    everyFiveMinutes
  packages/ticketing/src/TicketingServiceProvider.php:102 ExpireTransfersCommand       hourly
```

Everything below represents business rules that must fire on their own. **No human ever types
these commands** — they are cron jobs wearing a Command costume, and the scheduler was never
wired.

| Command | Consequence of not scheduling |
|---|---|
| `cashier-chip:renew-subscriptions` | **Subscriptions silently stop billing.** See #4. |
| `communications:dispatch-due` | Scheduled communications never send. See #2. |
| `affiliates:process-payouts` | Affiliate commissions never settle. |
| `affiliates:process-maturity` | Commissions never mature / release. |
| `affiliates:process-ranks` | Affiliates never promoted on qualification. |
| `affiliates:award-bonuses` | Performance bonuses never awarded. |
| `affiliates:aggregate-daily` | Daily affiliate stats go stale. |
| `engagement:send-due-reminders` | Reminders never dispatch. |
| `signals:process-alerts` | Alert rules never evaluate. |
| `signals:aggregate-daily` | Daily signal metrics never roll up. |
| `affiliate-network:sync-offers` | Site offer catalogues go stale. |
| `growth:recompute-assignments` | Experiment assignments drift. |
| `promotions:deactivate-expired` | Removed — see cleanup summary. Display is now derived. |

Only `affiliates` and `cashier-chip` document the obligation:

- `packages/affiliates/docs/11-commands.md` — a `Recommended Schedule` block for all five
  affiliate commands, plus a copy-paste `schedule()` implementation. **The only package of 69
  that documents its command surface.**
- `packages/cashier/docs/02-installation.md:218` — *"CHIP doesn't have native subscriptions.
  **Your app must schedule renewals.**"* with the snippet. Reinforced by
  `packages/cashier-chip/docs/99-troubleshooting.md:9` and the `Scheduler required: No | Yes`
  column in `packages/cashier/docs/05-subscriptions.md:18` and `07-multi-gateway.md:26`.

**Next action:** decide per command whether the *package* registers the schedule (matching the
`seating` / `ticketing` pattern) or documents it as a host obligation. Do not leave it
undocumented — that ambiguity is why 13 went unwired.

### 4. `cashier-chip:renew-subscriptions` is the entire billing engine

**Package:** `cashier-chip`

`packages/cashier-chip/src/Console/RenewSubscriptionsCommand.php:368` is the **only** place
that advances `next_billing_at` for an existing subscription. `CreateChipSubscription:93` sets
it at creation; `SyncChipPurchaseStatus:145` mirrors it from a purchase the command initiated.
There is no gateway auto-charge (`setup_future_usage`) and no listener.

The command also owns crash recovery: `failExpiredClaim()` (`:315-347`) unblocks the
`(subscription, period_key)` unique constraint, without which a lease-expired claim freezes a
subscription active-but-overdue forever (`:301-311`).

This is documented as mandatory, yet unscheduled (see #3). It is the single highest-risk item
after #1 and #2.

**Next action:** schedule it. Treat as production-blocking for any app taking CHIP payments.

---

## MEDIUM

### 5. `communications:expire` was deliberately kept — and it is also unscheduled

**Package:** `communications`

This is the one expiry command the cleanup did **not** remove, and the reason is structural
rather than a judgement call.

`communication_deliveries` has **no `expires_at` column**. Its timestamp columns are
`scheduled_at`, `queued_at`, `sending_at`, `sent_at`, `failed_at`. A delivery therefore cannot
derive its own expiry — it can only learn that its parent communication expired through the
cascade at `packages/communications/src/Console/Commands/ExpireCommunicationsCommand.php:96-119`:

```php
$communication->deliveries()
    ->whereNotIn('status', [Delivered, Opened, Read, Clicked, Replied,
                            Bounced, Complained, Failed, Cancelled,
                            Expired, Suppressed, Unsubscribed])
    ->chunkById(100, fn ($deliveries) => … TransitionDeliveryAction::handle(
        $delivery, DeliveryStatus::Expired, force: true));   // :116
```

Those rows are inert either way — `DispatchDueCommunicationsCommand:65-68` refuses to dispatch
a communication whose `expires_at` has passed, so the deliveries are never queued — but they
stay at `pending`/`scheduled` forever and inflate the pending bucket in
`DeliveryStatusOverviewWidget::cachedTotals()` (`:55`), which is status-only across five
buckets. Fixing that honestly needs a join to the parent's `expires_at`, not a derived column.

**Two concerns follow.**

1. It is on the unscheduled list in #3. Until it is wired, expired communications keep
   accumulating undelivered rows. The parent `Communication` status is cosmetic (the dispatcher
   already excludes by date), so the visible symptom is a wrong pending count, not a wrong send.
2. A stale `queued` delivery is **not** in `TransitionDeliveryAction::TERMINAL_STATUSES`, so
   `applyProviderStatus()` can still move it when a late provider webhook lands. Correctness is
   preserved only because the dispatcher never queued it in the first place. If the dispatch
   path ever changes, that coupling becomes load-bearing and undocumented.

**Next action:** schedule it (see #3). If the pending-count inaccuracy matters more than the
command, replace the status-only widget counts with a join on `communications.expires_at`
instead — then this command becomes deletable like its six siblings.

### 6. `commerce-support`'s `HasWebhookLifecycle` trait is dead and actively misleading

**Package:** `commerce-support`

`packages/commerce-support/src/Traits/HasWebhookLifecycle.php` is referenced by **no class** in
the repository — the only match is its own docblock example.

Worse, its semantics **conflict** with the hand-rolled replacements in `chip`:

| | `HasWebhookLifecycle` | `chip/src/Models/Webhook.php:153-189` |
|---|---|---|
| Retry status value | `status = 'retrying'` | `status = 'failed'` |
| Failure detail | `exception` | `last_error` |
| Counter | `retry_count` | (different field) |

Adopting the trait would silently change chip's webhook state machine. It reads like a shared
abstraction but is a trap.

**Next action:** delete it, or reconcile it with `chip` and adopt it everywhere. Leaving it as
is invites the mistake.

### 7. `MarkFeedbackInvitationOpenedAction` is unreachable

**Package:** `feedback`

`packages/feedback/src/Actions/MarkFeedbackInvitationOpenedAction.php:19` contains a genuine
status-only gate:

```php
if ($invitation->status !== Sent && $invitation->status !== Pending) { ... }
```

It has **zero callers in `src`** — the only reference outside itself is
`tests/src/Feedback/RegressionTest.php:189`. Notably it is the one reader that *would* have
justified a sweep command, and it is unreachable, so nothing does.

**Next action:** delete the action and its test, or wire it into the invitation-open flow.

### 8. `InviteMemberAction` recovery path skips the self-heal

**Package:** `membership`

The primary path self-heals a past-due invitation:

```
packages/membership/src/Actions/InviteMemberAction.php:53   ! $existing->expireIfDue()
```

The `QueryException` uniqueness-violation recovery path does not:

```
packages/membership/src/Actions/InviteMemberAction.php:82   ->firstOrFail()   // no expireIfDue()
```

`pendingInvitationQuery()` (`:95-103`) filters `where('status', Pending)` and ignores
`expires_at` entirely, so a past-due row can be returned as still-valid inside a narrow race
window.

**Next action:** filter `pendingInvitationQuery()` on the date rather than the status, or call
`expireIfDue()` on the recovered row.

### 9. `cart:clear-abandoned` is two unrelated commands in one 607-line file

**Package:** `cart`

`packages/cart/src/Console/Commands/ClearAbandonedCartsCommand.php` was left untouched. It is
the worst remaining offender and deserves its own cleanup:

- **Two commands behind one `--mark-only` switch** (`:26`, `:48`). The default branch physically
  deletes `carts` rows via three stacked selection modes (`--days`, `--expired`, `--delete`); the
  `--mark-only` branch sets `cart_snapshots.abandoned_at`. The two have no relationship to
  each other.
- **Ignores three existing model methods.** `CartModel::isExpired()` (`:122`),
  `CartModel::isAbandoned()` (`:146`) and `CartSnapshot::isAbandoned()` (`Snapshots/CartSnapshot.php:290`) all exist and
  none is called. The command re-derives both predicates from raw columns.
- **Bypasses Eloquent entirely.** Seven `DB::table()` calls (`:325`, `:569`, `:590`, `:595`,
  `:603`, …) with `CartOwnerScope::applyForOwner()` and hand-rolled `OwnerTupleColumns` /
  `OwnerTupleParser` tuple handling, plus a manual id-ordered batch-delete loop that duplicates
  what `chunkById()` already does safely.
- **Neither branch is scheduled**, so like most of #3 it never runs in production.

**Next action:** split into two commands, drive both from the model accessors, and move the
owner iteration onto the shared `OwnerBatchRunner`. Only then schedule it.

### 10. Filament pages dispatch commands by string name

**Package:** `filament-customers` (and the pattern is repeatable)

```
packages/filament-customers/src/Pages/SegmentRebuildPage.php:102
    Artisan::queue('customers:rebuild-segments', [...]);
packages/filament-customers/src/Pages/SegmentRebuildPage.php:127
    Artisan::queue('customers:rebuild-segments', $this->ownerCommandOptions());
```

A Filament page depends on a console command existing, coupled by a raw string. Nothing
resolves this at boot: rename or delete the command and the page still renders, then fails at
click time with a queue error. This audit nearly removed `customers:rebuild-segments` on the
reasoning that the model already self-heals — the Filament page is the only reason it is
load-bearing.

The same shape exists in `packages/filament-affiliate-network/src/Actions/SyncSiteCatalog.php`,
which calls `OfferImportService::syncAll()` directly (the safer pattern, since a class
reference fails loudly).

**Next action:** inject the underlying Action into the page rather than queueing a command by
name, or add a test that asserts every `Artisan::queue()` target is a registered command.

### 11. `jnt:order:create` cannot create a real shipment

**Package:** `jnt`

`packages/jnt/src/Console/Commands/Orders/OrderCreateCommand.php:47-48,54` hardcodes
`'Default Sender Address'` and `postCode: '50000'` and exposes no `--sender-address` /
`--postcode` override. It is a sandbox smoke test that reads as an operational tool, and it is
the only CLI path to `JntExpressService::createOrder()` — `filament-jnt`'s `JntOrderResource`
has `index` and `view` only, no create page.

**Next action:** add the real parameters, or delete it and document the Filament/service path.

### 12. Pre-existing test failure: `FeedbackSubmissionTest` concurrency

**File:** `tests/src/Feedback/FeedbackSubmissionTest.php:250`

```
it returns one submitted response when submissions race
  Failed asserting that actual size 1 matches expected size 2.
```

Reproducible 3/3 (not a flake). Confirmed **pre-existing**: fails identically on a clean
`git stash push` of the command-cleanup work. It forks parallel processes against a copied
SQLite database and expects 2 successful submissions; only 1 succeeds.

Unrelated to the command cleanup, and left untouched.

**Next action:** triage separately. Likely a real concurrency bug in
`SubmitFeedbackResponseAction`, or a test-harness issue with the parallel SQLite copy.

### 13. Four lifecycle events fire and have zero handlers

**Packages:** `vouchers`, `promotions`, `communications`, `feedback`

| Event | Dispatched at | Handlers |
|---|---|---|
| `VoucherExpired` | `vouchers/src/Actions/ExpireVoucher.php:40` | **0** |
| `PromotionDeactivated` | `promotions/src/Actions/DeactivatePromotion.php:28` | **0** |
| `CommunicationExpired` | `communications/src/Console/Commands/ExpireCommunicationsCommand.php:92` | **0** |
| `FeedbackInvitationExpired` | — | **class does not exist** |

`BlockExpired` and a membership `InvitationExpired` also **do not exist as classes**.

Several package docs promise "lifecycle transition for reporting or downstream jobs" — those
downstream jobs do not exist. `promotions:deactivate-expired` and `vouchers:expire` have since
been removed, which makes `PromotionDeactivated` and `VoucherExpired` fully unreachable unless a
host app calls the actions.

**Next action:** either wire handlers, or drop the event dispatches and correct the doc language.

### 14. `jnt:health` and `packages/jnt/src/Health/JntHealthCheck.php` overlap

**Package:** `jnt`

Two independent implementations of the same health logic: the `jnt:health` command does its own
config checks inline, and `JntHealthCheck` is a separate class. `jnt:config:check` was folded
into the command during this cleanup, which makes the duplication more visible.

**Next action:** have `jnt:health` delegate to `JntHealthCheck`, or delete the class.

---

## LOW

### 15. `cashier-chip` charges doc contradicts the code

**Package:** `cashier-chip`

`packages/cashier-chip/docs/06-charges.md:9` claims *"The canonical way to charge a customer is
via the `ChargeChipCustomer` Action. All `$user->charge()` calls delegate to it internally."*

False. `packages/cashier-chip/src/Concerns/PerformsCharges.php` never references
`ChargeChipCustomer`; it drives the gateway directly:

```
:62   $builder = Cashier::chip()->purchase()
:106  $purchase = Cashier::chip()->chargePurchase($purchase->id, $recurringToken)
```

`ChargeChipCustomer` is now reachable only from `RenewSubscriptionsCommand`, so the doc
matters more, not less.

**Next action:** correct the doc, or make `PerformsCharges` actually delegate.

### 16. Command documentation is near-absent across the monorepo

- **1 of 69 packages** documents its command surface (`packages/affiliates/docs/11-commands.md`).
- **23 of 69** mention `php artisan` at all in `04-usage.md`, most with 1–2 lines.
- A command surface that is not written down cannot be reviewed, and a scheduling obligation
  (J1) cannot be verified. This is why the audit took three passes and why #3 went unnoticed.

**Next action:** give any package that ships a scheduled or money-moving command a
`docs/11-commands.md`, starting with `cashier-chip`.

---

### 17. `authz` writes the same permission loop four times, from two sources of truth

**Packages:** `authz`, `filament-authz`

`Permission::findOrCreate()` across a guard list is written four times:

```
packages/authz/src/Console/Commands/SyncAuthzCommand.php:56
packages/filament-authz/src/Console/DiscoverCommand.php:116
packages/filament-authz/src/Console/SeederCommand.php:127
packages/filament-authz/src/Console/SeederCommand.php:253   (emitted into the generated seeder)
```

`validateGuards()` is duplicated near-verbatim between
`packages/filament-authz/src/Console/DiscoverCommand.php:130` and
`packages/authz/src/Console/Commands/SyncAuthzCommand.php:121` — same
`array_keys(config('auth.guards'))` + `in_array` filter, one written as
`array_values(array_filter(...))` and the other as a foreach.

`authz:discover` is worth calling out separately: it duplicates `authz:sync`'s entire write path
while reading a *different* permission source (Filament entities at runtime via
`EntityDiscoveryService`, versus the static `config('authz.sync')` list).

And `authz:seeder` is a read-DB-then-generate-code round trip that is superseded by `authz:sync`
reading config. Running both means maintaining the same permission set in two places — a config
file *and* a generated seeder.

**Next action:** extract one `SyncPermissionsAction` in `authz` and have all four call it. Pick
one source of truth — either config or discovery, not both.

### 18. Webhook maintenance is spread across four packages with no shared abstraction

**Packages:** `cashier`, `chip`, `communications`, `jnt`, `cashier-chip`

| Command | Lines | Touches |
|---|---|---|
| `cashier:webhook:replay` | 88 | no table — re-fetches one event from the gateway |
| `communications:replay-webhooks` | 182 | `communication_events` |
| `chip:retry-webhooks` | 109 | `webhook_calls` |
| `chip:clean-webhooks` | 82 | `webhook_calls` (delete) |
| `jnt:webhook:test` | 142 | no table — dev diagnostic |
| `cashier-chip:webhook` | — | deleted, see cleanup summary |

There is **no** shared retry/cleanup/replay base — `commerce-support` has
`Actions/ProcessWebhookCallAction`, `Webhooks/CommerceWebhookProcessor`,
`CommerceSignatureValidator` and `Contracts/Payment/WebhookHandlerInterface`, but nothing that
unifies retry or retention. That is not necessarily wrong: the three tables are unrelated, and
two of the commands touch no table at all.

The real hazard is the inverse of #6: `HasWebhookLifecycle` looks like the missing shared piece,
but adopting it would break chip (see #6). Someone will try.

**Next action:** either delete `HasWebhookLifecycle` and accept four local implementations with
a comment explaining why, or genuinely reconcile it across all four. Do not leave the trap.

### 19. `commerce:install` and `commerce:publish-migrations` overlap

**Package:** `commerce-support`

Both publish across packages and both accept `--tags=*`, `--list`, `--dry-run` and `--force`.
`commerce:install` publishes every detected publish tag (migrations plus config and assets);
`commerce:publish-migrations` publishes only migration tags but **preserves original
timestamps** so already-executed migrations are not re-run.

That timestamp behaviour is the real differentiator and it is easy to lose by reaching for
`commerce:install` after a package update, since `--tags=*` overlaps.

**Next action:** document the distinction at the top of both, and have `commerce:install` note
that it is not timestamp-safe for repeat runs.

### 20. `commerce:boost-install` is monorepo dev tooling shipped in a published package

**Package:** `commerce-support`

`packages/commerce-support/src/Commands/BoostInstallCommand.php` exists to work around a wrong
`base_path()` inside this monorepo and testbench: it resolves the project root, temporarily
rebases `app()->setBasePath()`, calls `useAppPath()`, patches the application namespace by
reflection, then restores all three in a `finally`.

Its own docblock says *"for Testbench/Monorepo environments"*. A host app does not need it —
`ilmu360` calls Laravel's own `boost:update` in its composer script and never touches
`commerce:boost-*`.

It was pulled from the deletion list during the audit because it is a matched pair with
`commerce:boost-update` (which `composer.json:285` invokes), not a duplicate. That reasoning
holds, but the packaging question stands.

**Next action:** move both commands to a dev-only package, or to the monorepo root, so they stop
shipping to consumers who will never run them.

### 21. `audits/` and `evidence/` still reference commands that no longer exist

**Root:** `audits/*.md`, `evidence/*.txt`

Roughly 30 audit and evidence files cite deleted commands — `evidence/php-lint-all.txt` alone
references most of them, and `audits/e2e-review-2026-09-13.md` is 459 KB. They are historical
records and were deliberately left untouched, but anyone grepping them will conclude commands
like `address:seed` still exist.

**Next action:** leave the history alone, but note at the top of `audits/README.md` that these
files are point-in-time and may cite removed commands.


## Documentation audit findings (2026-09-27)

Found by verifying every factual claim in the package docs against `src/`, `config/`, and
`database/migrations/`. Doc-side defects are already fixed. These are the code problems behind
them, ranked by blast radius.

### 22. `CRITICAL` — CHIP accepts unauthenticated webhooks in every non-production environment

**Package:** `chip`

`packages/chip/src/Webhooks/ChipSpatieSignatureValidator.php:20-27` honours
`chip.webhooks.verify_signature = false` in *any* environment except `production`:

```php
if (! $shouldVerify) {
    if (app()->environment('production')) { /* log + return false */ }
    return true;   // <-- staging, local, testing all accept anything
}
```

A staging endpoint therefore accepts forged payloads, and those payloads drive real writes:
`SyncChipPurchaseStatus` flips subscription state, `HandlePurchasePaid` creates renewals.

**Next action:** gate on `app()->environment('local', 'testing')` instead of excluding only
production, or require an explicit second opt-in for any shared environment.

### 23. `CRITICAL` — the documented `json_column_type` env var does nothing

**Package:** `commerce-support` (affects every package)

`config/commerce-support.php:15` reads `COMMERCE_SUPPORT_JSON_COLUMN_TYPE`, but
`commerce_json_column_type()` in `src/helpers.php:25-29` never consults that config value. It
resolves in this order:

1. `getenv('{PKG}_JSON_COLUMN_TYPE')`
2. `getenv('COMMERCE_JSON_COLUMN_TYPE')`
3. `config('{pkg}.database.json_column_type')`
4. the caller's `$default`

So setting `COMMERCE_SUPPORT_JSON_COLUMN_TYPE` — the name the config file and the docs both
publish — has no effect on any migration. Only the per-package and global env vars work.

**Next action:** either drop the misleading `env()` call from the config (steps 3 already read
the key) or make the helper consult `COMMERCE_SUPPORT_JSON_COLUMN_TYPE` as well.

### 24. `HIGH` — `Order::canCancel()` returns true for a state that cannot be cancelled

**Package:** `orders`

`Created::canCancel()` returns `true` (`src/States/Created.php:29`), and
`Order::canCancel()` delegates straight to it. But `Canceled` is only reachable from
`PendingPayment`, `Processing`, and `OnHold` (`src/States/OrderStatus.php:79,86,90`) — there is
no `Created → Canceled` edge. Any UI gating a cancel button on `canCancel()` offers an action
that throws.

`OrderStatus::config()` is `final`, so a consumer cannot add the missing edge.

**Next action:** either add the `Created → Canceled` transition, or return `false` from
`Created::canCancel()`.

### 25. `HIGH` — `WebhookReceived::eventType()` is null for every CHIP webhook

**Package:** `cashier`

```php
// src/Events/WebhookReceived.php:73-77
// Stripe uses 'type', CHIP might use 'event' or similar
return $this->payload['type'] ?? $this->payload['event'] ?? null;
```

CHIP sends `event_type` — confirmed at `chip/src/Services/WebhookEventDispatcher.php:69` and
`:127`. The in-code comment guesses wrong. Every CHIP webhook therefore reports a null event
type, so any log line, dashboard, or listener keyed on it is silently empty.

**Next action:** read `event_type` as well; better, extract the envelope per gateway.

### 26. `HIGH` — two billing actions report success without doing anything

**Package:** `filament-cashier-chip`

`src/Resources/InvoiceResource/Pages/ViewInvoice.php` — `download_pdf` (:37) and
`send_invoice` (:56) are stubs:

```php
// PDF generation would be handled here
Notification::make()->success()->send();
// Email sending would be handled here
Notification::make()->success()->send();
```

An operator clicks, sees a green success toast, and no PDF is produced and no mail is sent.
Worse than a visible error. `Invoice::pdf()` also throws unless a renderer is bound.

**Next action:** either implement both, or hide them and surface a clear "not configured"
notification.

### 27. `HIGH` — two packages use libraries they do not declare

**Packages:** `orders`, `shipping`

| Package | Uses | In `require`? |
|---|---|---|
| `orders` | `spatie/laravel-model-states` throughout `src/States` and `src/Transitions` | no |
| `shipping` | `lorisleiva/laravel-actions` (`AsAction`) in every `src/Actions/*` | no |

Both resolve only transitively today. `.ai/packages` requires packages to work standalone, so
a consumer pinning a different version of either library gets an undeclared-dependency break.

**Next action:** add both to `require`.

### 28. `HIGH` — `HasCommerceAudit` properties are untyped, so the obvious consumer code fatals

**Package:** `commerce-support`

`src/Concerns/HasCommerceAudit.php:47,54` declares:

```php
protected $auditInclude = [];
protected $auditExclude = [];
```

A consumer writing the natural `protected array $auditExclude = ['password'];` gets a PHP
fatal — *"type of X must be array (as in class Y)"*. The trait's own docblock example at :34
shows the untyped form, so the trap is well camouflaged.

**Next action:** type both as `array`. owen-it reads `$this->auditInclude ?? default`, so typing
is safe.

### 29. `MEDIUM` — four tables read a config key that is not in the map, so they ignore `table_prefix`

**Packages:** `affiliates`, `affiliate-network`, `shipping`

| Model | Reads | Present in config? |
|---|---|---|
| `affiliates` `AffiliatePayoutOperation` | `affiliates.database.tables.payout_operations` | no |
| `affiliates` `AffiliateWebhookDelivery` | `affiliates.database.tables.webhook_deliveries` | no |
| `affiliate-network` `NetworkConversionLeg` | `affiliate-network.database.tables.conversion_legs` | no |
| `shipping` `ShipmentOperation` | `shipping.database.tables.shipment_operations` | no |

Each falls back to a hardcoded name, so `AFFILIATES_TABLE_PREFIX` (and the other prefixes)
silently do not rename them while renaming the other 28 tables. Two of them cannot be
overridden by config at all, which breaks the pattern every sibling model follows.

**Next action:** add the four keys to their `tables` maps.

### 30. `MEDIUM` — `TaxZone::scopeForAddress()` accepts a postcode and ignores it

**Package:** `tax`

`src/Models/TaxZone.php:154` takes `?string $postcode = null`, but the query only closes over
`$country` and `$state`. No postcode predicate is emitted, and postcode patterns are matched in
PHP. A caller passing a postcode reasonably believes the result is postcode-filtered.

**Next action:** either apply the postcode range in SQL, or drop the parameter and document that
postcode matching happens downstream.

### 31. `MEDIUM` — stock lookup swallows every error and returns a wrong number

**Package:** `products`

`src/Models/Product.php:713-715`:

```php
} catch (Throwable) {
    // Inventory tables may not exist, fall back to local stock
}
```

No log, no metric, no rethrow. A missing table, a dropped column, or a connection failure all
present identically to "this product has no tracked stock" — so storefronts show 0 or a stale
local figure with no signal anywhere.

**Next action:** log at warning with the exception, or narrow the catch to the specific
`QueryException` that means "table absent".

### 32. `MEDIUM` — dead and orphaned config keys across six packages

**Defined but never read by any code:**

| Key | Package |
|---|---|
| `features.owner.customer_resolver` | `cashier-chip` |
| `invoices.vendor_address` | `cashier-chip` |
| `currency_locale` | `cashier-chip` |
| `offers.require_approval` | `affiliate-network` |
| `marketplace.show_commission_rates`, `marketplace.show_cookie_duration` | `filament-affiliate-network` |
| `carriers` | `filament-shipping` |
| `billing_portal.enabled`, `billing_portal.features.{payment_methods,invoices,gateway_switching}` | `filament-cashier` |
| `resources.snapshots.read_only` | `filament-addressing` |

**Read by code but absent from the shipped config** (so only the inline default is ever
effective): `cashier-chip.renewals.{chunk_size,lease_minutes}`,
`affiliates.webhooks.delivery.max_attempts`.

Two of these are worse than dead. `cashier-chip`'s `CustomerOwnerResolverInterface` docblock
claims `features.owner.customer_resolver` "points to a custom class" — but no `bind()` for that
interface exists anywhere in `src/`, so **custom customer-to-owner mapping is impossible as
shipped**. And `filament-cashier.billing_portal.enabled` cannot disable the portal; only
un-registering `BillingPanelProvider` does.

**Next action:** delete the dead keys, add the two missing ones, and either wire the customer
resolver or drop the interface.

### 33. `MEDIUM` — two classes named `VoucherStatus`

**Package:** `vouchers`

`src/Enums/VoucherStatus.php` (a backed enum, display-only) and `src/States/VoucherStatus.php`
(the abstract Spatie model-state the `status` cast actually resolves to). Both are imported as
`VoucherStatus` in consumer code, and passing the enum where the state is expected fails at
runtime. This has already caused at least one wrong doc example.

**Next action:** rename the enum to `VoucherStatusValue`, or move it under a namespace that
disambiguates.

### 34. `MEDIUM` — the universal webhook base only understands one gateway's envelope

**Package:** `commerce-support`

`CommerceWebhookProcessor::extractEventType()` (`src/Webhooks/CommerceWebhookProcessor.php:74`)
reads only `event_type`, falling back to the string `'unknown'`. Stripe uses `type`, PayPal uses
`event`. A consumer subclassing this "universal" base must override the extractor for every
gateway but CHIP.

**Next action:** make the extractor abstract, or add `type` and `event` to the fallback chain.

### 35. `MEDIUM` — targeting rules validate clean then silently behave as something else

**Package:** `commerce-support`

`Targeting/Evaluators/GeographicEvaluator.php` ignores unknown keys. A rule written as
`['regions' => [...], 'exclude_countries' => [...]]` passes `validateRule()` — which only
consults the operator enum — and then evaluates as a plain country rule, because the real keys
are `countries` / `values`. `ItemAttributeEvaluator` and `MetadataEvaluator` additionally return
`false` for any `TargetingContextInterface` implementation that is not the concrete
`TargetingContext`, so a custom context silently fails every `item_attribute`, `item_constraint`,
`metadata`, and `currency` rule.

**Next action:** reject unknown keys in `validateRule()`, and use the interface rather than the
concrete class in the context checks.

### 36. `LOW` — dashboard pages replace the whole panel widget list

**Package:** `filament-signals`

`SignalsDashboard::getWidgets()` overrides the panel's widget collection rather than extending
it, so any widget registered by the host app or another plugin disappears from that page.
`Filament\Pages\Page::getHeaderWidgets()` is the additive hook.

**Next action:** switch to `getHeaderWidgets()`.

### 37. `LOW` — `OrderStatus` docblock diagram contradicts the transition map

**Package:** `orders`

`src/States/OrderStatus.php:9-45` shows a "State Diagram" that omits 5 of the 21 real edges
(`Processing→Completed`, `Processing→Refunded`, `Shipped→Returned`, `Delivered→Refunded`,
`Completed→Refunded`) and draws `COMPLETED` as a sink. Anyone reading the code rather than
executing it gets the wrong model. Related to #24.

**Next action:** regenerate the docblock from `config()`.

### 38. `LOW` — colliding navigation sorts

**Package:** `filament-tax`

`config/filament-tax.php:52-53` sets `resources.navigation_sort.classes` and `.rates` both to
`2`, so those two resources order arbitrarily. `filament-addressing` has the same shape:
`PostalCodeResource` and `AddressSnapshotResource` both compute `sort + 3`, and three more
resources all use `+ 0`.

**Next action:** assign distinct offsets, or use the `navigation.offsets` map the resources
already read.

### 39. `LOW` — `products.slug` is required and never generated

**Package:** `products`

`slug` is `string NOT NULL` with no database default, and `Product::booted()` does not populate
it. Every `Product::create()` without an explicit slug is a runtime failure with no warning. The
same shape applies to `seating`'s `SeatSection.capacity`.

**Next action:** generate a slug in `booted()` from the name, as `AddressCountry` does.

### 40. `LOW` — `EventLocation::$address_snapshot` has no column

**Package:** `events`

`src/Models/EventLocation.php:50,96` declares `address_snapshot` in its `@property` block and
casts it to `array`, but no migration in `packages/events/database/migrations/` creates that
column and it is not in `$fillable`. Any read or write hits a missing column. Address data
belongs to the `HasAddresses` trait instead.

**Next action:** drop the property and cast.

### 41. `LOW` — stray blank lines in a bundled dataset

**Package:** `addressing`

`resources/geography/norway-postal-codes.csv` has 5,132 blank lines interleaved between its
5,136 data rows. Harmless today only because `CsvPostalCodeSource::postalCodes()` skips empty
codes; every other bundled CSV is clean.

**Next action:** strip the blank lines.

### 42. `MEDIUM` — passes never expire, so the seat-release listener is unreachable

**Package:** `ticketing`

`PassStatus::Expired` and `States/Expired` both exist, `Pass::markExpired()` transitions and
dispatches `PassExpired`, and `TicketingServiceProvider:95` wires
`ReleaseSeatsOnPassExpired` to it. But `markExpired()` has **no caller anywhere in `src/`** —
only `tests/src/Ticketing/Feature/*Lifecycle*.php`.

So a pass past its expiry keeps whatever status it had. Seats held by that pass are never
released, and the listener that would release them never runs.

**Next action:** add a `ticketing:expire-passes` sweep, or dispatch the expiry lazily from
`Pass::isValid()` and the check-in lookup.

### 43. `MEDIUM` — money returned as `float`

**Package:** `vouchers`

`src/Traits/InteractsWithVouchers.php:243` declares `getVoucherDiscount(): float`, while
`Voucher::getVoucherDiscount()` works in integer minor units. Two same-named methods on the same
model returning different types, one of them a float, in a repo whose rule is integer minor units
plus an explicit ISO 4217 code.

**Next action:** return `int` and let `MoneyFormatter` handle display.

### 44. `MEDIUM` — `Cart` facade types money as `float` and omits most of its own API

**Package:** `cart`

`src/Facades/Cart.php:40-42` declares `getRawSubtotal()`, `getRawTotal()`, and
`getRawSubtotalWithoutConditions()` as returning `float`. All three return `int` in
`src/Traits/CalculatesTotals.php:79,87,95`. The same docblock also omits `search()`,
`totalWithoutConditions()`, `removeShipping()`, `getShipping()`, `getShippingMethod()`,
`getShippingValue()`, `setMetadata()`, and `hasMetadata()` — all real, all forwarded via
`__callStatic`.

**Next action:** correct the three return types and complete the `@method` list.

### 45. `MEDIUM` — `matchesAddress()` has the same name and incompatible signatures in two packages

**Packages:** `shipping`, `tax`

```php
shipping/src/Models/ShippingZone.php:169
public function matchesAddress(AddressData $address): bool

tax/src/Models/TaxZone.php:182
public function matchesAddress(string $country, ?string $state = null, ?string $postcode = null): bool
```

A consumer generalising over "does this zone match this address" will break on one of them, and
the compiler will not catch a swapped call because both are `bool`-returning.

**Next action:** rename one (e.g. `matchesAddressData`), or give both a shared contract in
`commerce-support`.

### 46. `MEDIUM` — the tax settings form's fallbacks contradict the seeded defaults

**Package:** `filament-tax`

`src/Pages/ManageTaxSettings.php:67-73` falls back to `defaultTaxRate = 0.0`,
`defaultTaxName = 'Tax'`, and `taxIdLabel = 'Tax ID'`. The settings migration seeds
`6.0` and `'SST'` (`tax/database/settings/2026_06_13_000003_create_tax_settings.php:12-13`).

If the settings rows are missing, the form renders a **0% tax named "Tax"** rather than the 6%
SST the package actually intends for the default market — a silent wrong-default in a
revenue-affecting field.

**Next action:** align the fallbacks with the migration, or drop them so the settings row is the
single source.

### 47. `LOW` — tax registers migrations twice over

**Package:** `tax`

`src/TaxServiceProvider.php:23-24` sets both `->runsMigrations()` and `->discoversMigrations()`.
Discovery already implies running, so the first is redundant. Harmless today, but it reads as
uncertainty about which mechanism is in effect.

**Next action:** drop `runsMigrations()`.

### 48. `LOW` — a variant can be silently re-parented to another product

**Package:** `products`

`Variant::$fillable` includes `product_id` (`:90`) and the model has no `updating` hook, so
nothing prevents a variant from being moved to a different product. That orphans its option and
pivot rows, which are keyed to the original pairing.

**Next action:** add an `updating` guard, or drop `product_id` from `$fillable` and require an
explicit re-parent action.

### 49. `LOW` — `feedback` reads a config key that does not exist

**Package:** `feedback`

`src/Actions/CreateFeedbackFormFromTemplateAction.php:26` reads
`config('feedback.owner.include_global_templates', false)`. No such key exists in
`config/feedback.php`, so the `includeGlobal` branch is permanently `false` and the
include-global-templates behaviour is unreachable.

**Next action:** add the key or delete the read.

### 50. `LOW` — command help text points at the wrong config path

**Package:** `links`

`src/Console/Commands/PruneLinkClicksCommand.php:14` tells the operator the default comes from
`links.retention.prune_clicks_after_days`. Line 23 actually reads
`links.features.retention.prune_clicks_after_days`. Anyone tuning the wrong key sees no effect.

**Next action:** correct the help string.

### 51. `LOW` — card expiry accessors always return null

**Package:** `cashier-chip`

`src/Payment/PaymentMethod.php:110-113` — `expirationYear()` and `expirationMonth()` are
hardcoded `return null` behind docblocks reading "Get the expiration month (if available)". The
`cardExpMonth` / `cardExpYear` aliases point at the same accessors, so every caller gets `null`.

**Next action:** either populate them from the stored value or remove them.

### 52. `LOW` — MRR charts hardcode a two-decimal currency

**Package:** `filament-cashier-chip`

`src/Widgets/MRRWidget.php:47,132,135` and `src/Widgets/RevenueChartWidget.php:110,117` divide
minor units by a literal `100`. The sibling `formatCurrency()` path is currency-aware via
`MoneyFormatter::precisionFor()`. A JPY or KWD chart is wrong by two orders of magnitude.

**Next action:** scale by the currency's precision, as the formatter already does.

### 53. `LOW` — two dead config keys in `filament-events`

**Package:** `filament-events`

`config/filament-events.php:20` ships `resources.enabled.ticket_type` and `:34` ships
`resources.navigation_sort.ticket_type`. `FilamentEventsPlugin` never reads either — so setting
`enabled.ticket_type => false` hides nothing.

**Next action:** remove both, or wire them.

### 54. `LOW` — composer autoload points at directories that do not exist

**Package:** `filament-docs`

`composer.json` maps `AIArmada\FilamentDocs\Database\Factories\` to `database/factories/` and
`…\Seeders\` to `database/seeders/`, but the package has no `database/` directory at all.

**Next action:** drop the two PSR-4 entries, or add the directories.

### 55. `LOW` — one payment contract takes `Money` while everything else takes minor units

**Package:** `commerce-support`

`src/Contracts/Payment/PaymentGatewayInterface.php:92,103` accept `?Money` for `refundPayment()`
and `capturePayment()`. The rest of the package is integer minor units —
`MoneyNormalizer::toCents(int)`, `MoneyFormatter::decimalFromMinor(int, ?string)`. A gateway
implementation must decompose the object to talk to a PSP.

**Next action:** change to `?int $amountMinor, string $currency`.

### 56. `LOW` — `orders` adds database-level unique constraints

**Package:** `orders`

`database/migrations/2000_11_01_000001_create_orders_table.php:17-18` adds
`$table->string('order_number')->unique()` and `invoice_number->nullable()->unique()`. `.ai/database`
states "Never add database-level constraints or cascades". Idempotency is a legitimate reason, so
this is probably a deliberate exception — but it is not recorded as one, and the same file adds a
`(owner_type, owner_id, intake_source, intake_id)` unique index.

**Next action:** record the exception in the migration comment, or move enforcement to the
application layer per the repo rule.

### 57. `LOW` — an event class that is never dispatched

**Package:** `vouchers`

`src/Events/VoucherRefilled.php` defines `VoucherRefilled` and nothing in `src/` dispatches it.
`docs/09-usage-tracking.md` documents it alongside events that do fire.

**Next action:** dispatch it from whatever refills a voucher, or delete it.

### 58. `LOW` — checkout exposes an inventory commit path that nothing calls

**Package:** `checkout`

`src/Integrations/InventoryAdapter.php:39` implements `commit()`, but only `reserve()` is called
(`src/Steps/ReserveInventoryStep.php:132`). The real commit happens elsewhere:
`inventory/src/InventoryServiceProvider.php:258` binds `CommitInventoryOnPayment` to the orders
`InventoryDeductionRequired` event.

So checkout holds a parallel, unused commit seam, and a reservation made by the checkout step is
only ever released on failure, never committed by checkout itself.

**Next action:** delete `InventoryAdapter::commit()` and document that commit is
inventory's job, driven by the orders event.

### 59. `LOW` — dead enum

**Package:** `checkout`

`src/Enums/CheckoutFinalizationPhase.php` is referenced by exactly one file: itself.

**Next action:** delete it.

### 60. `LOW` — capacity-blocking fallback omits a status the config includes

**Package:** `events`

`src/Support/Policy/LifecyclePolicy.php:67` falls back to
`['pending', 'confirmed', 'checked_in']`, while `config/events.php:244` ships
`['pending', 'confirmed', 'refund_pending', 'checked_in']`. If the config key is ever absent —
a published-but-trimmed config, a cache-cleared config — a `refund_pending` registration
silently stops blocking capacity.

**Next action:** add `refund_pending` to the inline fallback.

### 61. `LOW` — stale comment naming a class that does not exist

**Package:** `cart`

`src/Snapshots/CleanupSnapshotOnCartMerged.php:90` says the snapshot is cleaned up "by the normal
cart sync listeners (SyncCompleteCart, etc.)". There is no `SyncCompleteCart`; the real classes
are `Snapshots/SyncCartOnEvent` and `Snapshots/SyncNormalizedCartJob`.

**Next action:** fix the comment.

### 62. `LOW` — `generateQrCode()` and `generateBarcode()` generate neither

**Package:** `ticketing`

`src/Models/Pass.php:305-313`:

```php
public function generateQrCode(): string  { return Str::random(32); }
public function generateBarcode(): string { return (string) str()->random(16); }
```

Both are unused, and both return random strings rather than an encoded payload. The real values
are set at issuance — `DefaultPassIssuer.php:165` assigns `Str::uuid()` to `qr_code` — and the
`ticket_passes` table has `qr_code` / `barcode` columns. So the lookup key is fine; only the
method names promise something the code does not do.

**Next action:** delete both, or rename to what they are.

### 63. `LOW` — a factory writes a column the model does not have

**Package:** `growth`

`database/factories/ExperimentFactory.php:37,69` sets `'is_active' => true`. `Experiment` has no
`is_active` column or fillable entry — activity is `status` + `ExperimentStatus` (`:88,103`).
The factory state is silently dropped, so any test relying on it is not testing what it appears to.

**Next action:** set `status => ExperimentStatus::Active->value` instead.

### 64. `LOW` — a config near-miss that only works by accident

**Package:** `checkout`

`config/checkout.php:244-247` nests under `routes.webhooks` (plural) but the value reads
`'config' => 'checkout.webhook.chip'` (singular). It resolves only because the value is a spatie
webhook-client config *name*, never a `config()` lookup. Rename one side and it breaks silently.

**Next action:** make the value a real key or rename the tree node to match.

### 65. `LOW` — comment contradicts the code it sits above

**Package:** `inventory`

`src/Models/InventoryLevel.php:84` says "quantity_reserved is deliberately absent", but
`:149` computes `available` from `quantity_reserved` and `:261` increments it. The comment
describes an older design and invites someone to "fix" the fillable list and break reservations.

**Next action:** rewrite the comment to explain that reserved stock is mutated through the
dedicated increment path, not mass assignment.

### 66. `LOW` — deprecated Filament v5 APIs still in use across adapters

**Packages:** `filament-affiliate-network`, `filament-addressing`, `filament-cart`,
`filament-signals`

`->actions([...])` on a `Table` is a deprecated forwarder for `recordActions()` — present in at
least five `filament-affiliate-network` relation managers and tables, plus
`filament-addressing/src/RelationManagers/AddressesRelationManager.php`. Separately,
`RecentActivityWidget` (`filament-cart`) and `PendingSignalAlertsWidget` (`filament-signals`)
still use `protected int|string|array $columnSpan`, where `.ai/filament` prescribes the
`getColumnSpan()` accessor. All currently function in 5.8.x.

**Next action:** migrate when convenient; not urgent.

---

## Cleanup summary

Removed 18 commands, 2 orphaned Actions, 3 test files. Added 1 test file, 3 model primitives,
2 command folds, and rewrote 10 Filament surfaces.

**Deleted as exact duplicates** (replaced, not lost):

| Deleted | Replacement |
|---|---|
| `address:seed`, `-countries`, `-states`, `-cities`, `-country-references` | `AddressingSeeder` / `AddressCountrySeeder` |
| `commerce:seed-currencies`, `-languages`, `-timezones` | `CurrencySeeder` / `LanguageSeeder` / `TimezoneSeeder` |
| `address:import-areas-csv` | `address:import-areas --csv=` + `--source-key=` |
| `cashier-chip:webhook` | read `config/cashier-chip.php` |
| `jnt:config:check` | folded into `jnt:health` (private-key + base-URL format validation) |

**Deleted as non-load-bearing**, after confirming every correctness path derives from the date
column rather than the stored status:

| Deleted | Why it was safe |
|---|---|
| `events:finalize-orders` | Both default resolver bindings write nothing; a listener already covers it |
| `moderation:expire-blocks` | `scopeExpired()` and the `HasBlocks` delete hook both cover it; no `BlockExpired` event exists |
| `membership:expire-invitations` | `expireIfDue()` self-heals; `InviteMemberAction:53` already calls it |
| `feedback:prune-expired-invitations` | 3 lazy self-heal paths; no expired event class |
| `promotions:deactivate-expired` | `scopeActiveAt()` is canonical and date-aware |
| `vouchers:expire` | `VoucherValidator` checks `isExpired()` *before* status |

Display honesty for the last four now comes from derived model reads rather than a nightly
sweep: `FeedbackInvitation::effective_status`, `Promotion::scopeCurrentlyActive()` +
`is_currently_active`, `Voucher::effective_status`.

**Deliberately kept:** `communications:expire` — `communication_deliveries` has no `expires_at`
column, so a delivery can only learn its communication expired via that cascade.

**Deliberately not deleted:** `commerce:boost-install` — a matched pair with
`commerce:boost-update`, both wrapping a different Boost command behind the same monorepo
path-rebasing workaround (`setBasePath` + `useAppPath` + reflection-patched app namespace,
restored in a `finally`).

## Verification performed

- 5045 tests pass across the 14 affected packages; 1 failure (#9) proven pre-existing via stash
- PHPStan level 6 clean on all 32 touched files; 8 packages independently confirmed clean
- Pint clean on all 32 touched files
- Zero residual references to any deleted command or class outside `audits/` and `evidence/`
- No orphaned config keys (`jnt.base_urls` went 3 readers → 2, still live)
