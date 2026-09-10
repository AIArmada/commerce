# Engagement Audit — DONE (2026-09-11)

## Verdict

The engagement pair (`engagement` + `filament-engagement`) is
disposition-complete. Every rated finding A1–A4, C1–C2, and the Filament,
security, and performance checks is implemented, verified, or explicitly
deferred below. No rated findings remain open.

## What was done

- **A1 — IMPLEMENTED.** `EngagementManager` now binds actors to
  `CanInteract` and subjects to their marker contracts
  (`packages/engagement/src/Contracts/EngagementManager.php:15-45`). The
  concrete manager uses the same signatures and validates model contracts at
  its boundary (`packages/engagement/src/Services/DefaultEngagementManager.php:62-64,516-520`).
  Filament's eight action entry points resolve submitted records through
  `ActionRecordResolver::resolveOrFail()` (`packages/filament-engagement/src/Support/ActionRecordResolver.php:20-41`; action call sites at
  `FollowAction.php:19`, `UnfollowAction.php:21`, `BookmarkAction.php:19`,
  `RemoveBookmarkAction.php:21`, `ReactAction.php:25`, `RespondAction.php:25`,
  `SubscribeAction.php:19`, and `SetReminderAction.php:26`).
- **A2 — IMPLEMENTED.** All 14 public `Can*`/`Has*` trait names remain; the
  actor-side and subject-side implementations are shared by
  `InteractsWithEngagement` (`packages/engagement/src/Traits/InteractsWithEngagement.php:15-45`)
  and `ReceivesEngagement` (`packages/engagement/src/Traits/ReceivesEngagement.php:13-55`).
  `tests/src/Engagement/TraitDelegateParityTest.php` verifies parity.
- **A3 — IMPLEMENTED / DROPPED AS FALSIFIED.** The phantom
  `MatchSubscriptionsOnEventOccurrencePublished` listener is absent and
  repo-scoped search finds no remaining coupling. The intentional boundary is
  documented at `packages/engagement/docs/04-usage.md:300-304`: event
  attendance intent and social-engagement intent remain separate, with no
  automatic translation. No `events/` file was changed.
- **A4 — IMPLEMENTED.** Reminder scheduling remains in
  `packages/engagement/src/Console/Commands/SendDueRemindersCommand.php:28-73`;
  delivery uses communications' managed dispatch and reminder context in
  `packages/engagement/src/Listeners/DispatchReminderThroughCommunications.php:24-68`.
- **C1 — IMPLEMENTED.** Both batch commands use `OwnerBatchRunner`
  (`MatchSubscriptionsCommand.php:58-81` and
  `SendDueRemindersCommand.php:32-73`); chunked iteration is retained in
  `DefaultSubscriptionManager.php:139-152` and
  `SendDueRemindersCommand.php:47-73`. The hand-rolled owner sniff/strip/
  re-enter blocks are gone.
- **C2 — IMPLEMENTED.** Manager writes run in transactions
  (`packages/engagement/src/Services/DefaultEngagementManager.php:66-79`),
  counter listeners are registered centrally
  (`packages/engagement/src/EngagementServiceProvider.php:109-122`), and the
  repair cadence is documented at `packages/engagement/docs/04-usage.md:280-283`.
- **Filament/security/performance — IMPLEMENTED.** Action records are
  re-resolved owner-safely (`ActionRecordResolver.php:20-41`), the reminder
  listener revalidates the derived owner (`DispatchReminderThroughCommunications.php:24-30`),
  and the overview widget uses owner-specific caching plus owner-scoped
  aggregates (`packages/filament-engagement/src/Widgets/EngagementOverviewWidget.php:24-53`).

## Verification

- Engagement Area: `./vendor/bin/pest --parallel tests/src/Engagement` — **47 passed, 157 assertions**.
- FilamentEngagement Area: `./vendor/bin/pest --parallel tests/src/FilamentEngagement` — **5 passed, 30 assertions**.
- The Area suites were escalated after the targeted checks because the
  manager signature/boundary changes, listener deletion, and batch rewiring
  cross the core/Filament surfaces.
- Targeted files, each run with `--parallel`: `ContractBoundaryTest.php`,
  `TraitDelegateParityTest.php`, `ReminderCommandTest.php`,
  `CounterAfterWriteTest.php`, `CounterReconciliationTest.php`,
  `ActionsSecurityTest.php`, `PluginSurfaceTest.php`,
  `OwnerWriteBoundaryTest.php`, `CrossTenantIsolationTest.php`,
  `EngagementReminderNotificationTest.php`, and
  `SubscriptionMatchingTest.php` (rerun with `--processes=1` after an
  eight-worker process crash warning).
- PHPStan: `./vendor/bin/phpstan analyse packages/engagement/src packages/filament-engagement/src --level=6` — **clean**.
- Events canary: `./vendor/bin/pest --parallel tests/src/Events/EventLifecycleWorkflowTest.php` — **4 passed, 4 assertions**.
- Cart canary: `./vendor/bin/pest --parallel tests/src/Cart/Feature/Conditions/ConditionProviderRegistryTest.php` — **1 passed, 2 assertions**.

## Audit deviations

- The prescribed `.ai/rules/index.md` is absent in this checkout; no matching
  path rules could be loaded.
- `evidence/php-lint-all.txt:1430` contains a stale literal reference to the
  deleted listener. It is outside the engagement write set and was not edited.
- The communications-side reminder normalizer remains an external dependency;
  engagement continues to use the communications dispatch path and does not
  fork delivery logic.
- The initial parallel `SubscriptionMatchingTest` run emitted a worker crash
  warning; the single-process parallel rerun passed. No unrelated or full-repo
  suite was run.

## Residual notes

None open. Re-open the audit if the communications normalizer dependency or
the event/engagement boundary changes.
