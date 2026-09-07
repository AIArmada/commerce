# Engagement Audit

## Packages Reviewed (bullets)
- `packages/engagement` (`aiarmada/engagement`) — generic social-engagement domain: follows, bookmarks (+collections/items), responses, reactions, subscriptions, reminders, shares, counters
- `packages/filament-engagement` (`aiarmada/filament-engagement`) — Filament v5 admin: 7 resources, 6 relation managers, 8 table actions, overview widget

## Overall Assessment (quality, health, risks, refactor size)
- Quality: moderate. Good bones: all 10 models `HasOwner` (`engagement.owner`), `OwnerContext::withOwner` in commands/listeners, `withoutOwnerScope` used deliberately in batch commands, `CarbonImmutable`, UUID PKs, `getTable()` from config (spot-checked pattern), `json_column_type` defined, counter-uniqueness scoping migration (`000010_scope_engagement_counter_uniqueness_to_owner`) shows ownership was retrofitted thoughtfully.
- Health: functional but coarse. `DefaultEngagementManager` is a ~400-line god service with every public method taking `mixed $actor, mixed $subject`; 14 traits (`Can*` × 7 + `Has*` × 7) double the API; `ModelResolver` + stringly morph handling reimplements polymorphic resolution per method; `MatchSubscriptionsCommand` strips `OwnerScope` then re-enters per-owner (correct but fragile); zero tests.
- Risks: (1) `mixed`-typed actor/subject on all manager methods defers validation to runtime — wrong morphs fail deep inside, not at the boundary; (2) events package consumes only `EventEngagementManager` via `NullEventEngagementManager` default — the real `EngagementEventEngagementManager` bridge is one-sided (engagement listens to event publication but events never calls back); (3) reminder delivery (`SendDueRemindersCommand`, `EngagementReminderNotification`) overlaps `communications` delivery without using it.
- Refactor size: S–M (2–3 days). Type the manager boundary, collapse traits, bridge reminders through comms (or document split), tests.

## Migration Impact
**Migration Required: NO**
- Tables/columns: no changes. 11 migrations (follows, bookmarks, collections, collection-items, responses, reactions, subscriptions, reminders, counters, shares + counter-owner-uniqueness scoping) all use `uuid('id')->primary()`.
- Indexes/constraints: no FK constraints/cascades in migrations (verified) — compliant. Counter-uniqueness migration already scopes uniqueness to owner — good, no follow-up.
- Data migration: none. Manager typing + trait consolidation are code-only.

## Package Responsibilities
- Core owns: engagement primitives (`Services/DefaultEngagementManager.php` — follow/unfollow/mute, bookmark/archive/collections, respond/cancel, react/remove, remind/share), subscription matching (`Services/DefaultSubscriptionManager.php`, `Console/Commands/MatchSubscriptionsCommand.php`, `Listeners/MatchSubscriptionsOnEventOccurrencePublished.php`), reminders (`Services/DefaultReminderManager.php`, `SendDueRemindersCommand`, `Notifications/EngagementReminderNotification.php`), counters (`Services/DefaultEngagementCounterService.php`, `ReconcileEngagementCountersCommand`), policy/state resolution (`DefaultEngagementPolicyResolver`, `DefaultEngagementStateResolver`), share URLs (`DefaultShareUrlGenerator`), events bridge (`Integrations/Events/EngagementEventEngagementManager.php`), actor/subject traits (`Traits/Can*.php`, `Has*.php`), contracts per primitive.
- Filament adapter owns: 7 resources + view pages, 6 relation managers, 8 row actions (`FollowAction/UnfollowAction`, `BookmarkAction/RemoveBookmarkAction`, `ReactAction`, `RespondAction`, `SubscribeAction`, `SetReminderAction`), overview widget.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)
1. Severity: High. Location: `packages/engagement/src/Services/DefaultEngagementManager.php:50-423` (every method `mixed $actor, mixed $subject, array $options = []`) + `packages/engagement/src/Support/ModelResolver.php` + `Contracts/CanInteract.php`, `Followable.php`, `Bookmarkable.php`, `Reactable.php`, `Respondable.php`, `Remindable.php`, `Shareable.php`, `Subscribable.php`. Problem: 8 marker contracts exist but the manager accepts `mixed` instead of requiring them — validation happens inside via `ModelResolver` rather than at the signature. Why It Matters: passing a non-engageable model fails late with an obscure resolver error; static analysis (PHPStan L6) can't help; Filament actions pass IDs that resolve inside. Recommended Fix: type manager methods against the marker contracts (`Followable $subject`, `CanInteract $actor` — introduce `CanInteract` as the actor bound since `Contracts/CanInteract.php` already exists), keep `mixed` overloads only where truly polymorphic input is needed and validate with `assert($x instanceof ...)` + `InvalidArgumentException` at method entry. Breaking Change: YES (signatures). Affected Packages: `filament-engagement` (8 actions call the manager), `events` (bridge). Required Dependent Changes: update action call sites to pass resolved models (they already resolve for tables — move resolution before the call). Migration Required: NO.
2. Severity: Medium. Location: `packages/engagement/src/Traits/CanBookmark.php`, `CanFollow.php`, `CanReact.php`, `CanRespond.php`, `CanSetReminders.php`, `CanShare.php`, `CanSubscribe.php` + `HasBookmarks.php`, `HasFollowers.php`, `HasReactions.php`, `HasReminders.php`, `HasResponses.php`, `HasShares.php`, `HasSubscriptions.php` (14 traits). Problem: `Can*` (actor capabilities) vs `Has*` (subject relations) split is conceptually right, but 14 traits for 8 primitives with overlapping helpers (each `Can*` re-resolves owner/model). Why It Matters: host models `use` 3–7 traits; shared logic (owner resolution, counter bumps) duplicated per trait. Recommended Fix: keep the 14 public trait names (they're the documented DX) but make each a 5-line delegate to two internal helpers (`InteractsWithEngagement` for actors, `ReceivesEngagement` for subjects); move shared query/counter logic there. Do NOT delete public traits (they're per-primitive opt-in, genuinely used). Breaking Change: NO. Affected Packages: none external. Required Dependent Changes: none. Migration Required: NO.
3. Severity: Medium. Location: `packages/engagement/src/Integrations/Events/EngagementEventEngagementManager.php`, `Listeners/MatchSubscriptionsOnEventOccurrencePublished.php`, `packages/events/src/Contracts/EventEngagementManager.php`, `packages/events/src/Integrations/NullEventEngagementManager.php`, `EventsServiceProvider.php` (events-side wiring). Problem: bridge direction is engagement→events only (engagement listens for event publication to match subscriptions); events' RSVP/interested flows (`Interested` registration status, `PromoteInterestedToConfirmedAction`) don't write back into engagement responses/follows. Why It Matters: "interested" in events and "subscribed/responded" in engagement diverge for the same user+event. Recommended Fix: wire events' `Interested` registration creation through `EngagementEventEngagementManager::respond()` (or document that they are intentionally separate: events = transactional attendance intent, engagement = social graph — then REMOVE the listener to cut the phantom coupling). Decide one; the current half-bridge is the worst option. Breaking Change: NO (either direction is wiring/docs). Affected Packages: `events`. Required Dependent Changes: events emits or engagement stops listening. Migration Required: NO.
4. Severity: Low. Location: `packages/engagement/src/Services/DefaultReminderManager.php`, `Console/Commands/SendDueRemindersCommand.php`, `Notifications/EngagementReminderNotification.php` vs `communications` package. Problem: reminder scheduling/delivery reimplements due-dispatch + notification sending that communications owns (batches, deliveries, quiet-hours, rate limits). Why It Matters: reminders ignore comms-level suppressions/preferences/quiet-hours (or reimplement them worse). Recommended Fix: keep reminder *scheduling* (due detection, `ReminderDue`/`ReminderSent` events) in engagement; deliver via communications `DispatchManagedNotificationAction`/manager with reminder context as reference (same bridge pattern as events audit A4). Breaking Change: NO (additive). Affected Packages: `communications`. Required Dependent Changes: comms reference normalizer for reminders. Migration Required: NO.

## Code Quality Findings (same finding format)
1. Severity: Medium. Location: `packages/engagement/src/Console/Commands/MatchSubscriptionsCommand.php:52-77` (`class_uses_recursive($modelClass)` sniffing for `HasOwner`, then `withoutGlobalScope(OwnerScope::class)` + manual `OwnerContext::withOwner`). Problem: correct behavior via fragile runtime sniffing; duplicates the `OwnerBatchRunner` pattern seating/ticketing already encapsulate. Why It Matters: every new batch command reinvents owner-iteration. Recommended Fix: rewrite both batch commands (`MatchSubscriptionsCommand`, `SendDueRemindersCommand`) on `OwnerBatchRunner` (commerce-support, already used by seating) — delete the hand-rolled sniff/strip/re-enter blocks. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `packages/engagement/src/Services/DefaultEngagementCounterService.php` + `Models/EngagementCounter.php` + `ReconcileEngagementCountersCommand`. Problem: counter caches alongside live relations without documented invalidation (reconcile command exists — good — but nothing shows writers bump counters synchronously). Why It Matters: stale counts on high-traffic subjects. Recommended Fix: verify manager write paths update counters in-transaction (or explicitly document reconcile-as-source-of-truth with cadence); add test pinning counter-after-write. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Laravel-Specific Findings
- PHP 8.4, strict types, UUID PKs, `HasUuids`, `getTable()` from config, `json_column_type` (`engagement.database.json_column_type` verified): PASS. No FK constraints/cascades, no soft deletes: PASS.
- Owner scoping: all 10 models `HasOwner` + `HasOwnerScopeConfig` (`engagement.owner`): PASS — exemplary coverage (compare affiliates 12/30, events 7/64).
- `DB::table` audit: no raw `DB::table` in engagement src (verified none found) — analytics go through Eloquent. PASS.
- `CarbonImmutable` in commands/services: PASS. Enum-per-primitive statuses (`FollowStatus`, `BookmarkStatus`, ...): consistent, no enum/state split. PASS.
- Factories exist for all 10 models (`database/factories/*` × 10) — the only audited core package with full factory coverage. PASS (but zero tests consume them — see Testing).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)
- Thin-adapter: PASS. 8 actions are thin delegates to the core manager (verified names mirror manager methods); resources are list/view; relation managers expose engagement per host.
- Domain leak: none found. `EngagementOverviewWidget` aggregates — verify `OwnerUiScope` on its queries (pattern used elsewhere; confirm during refactor).
- Duplication: actions mirror manager 1:1 — correct adapter shape, keep.
- Dependency direction: CORRECT (requires `aiarmada/engagement`). PASS. Navigation: PASS (`getNavigationGroup` from nested config on all 7 resources sampled).
- Owner scoping: GOOD. All 7 resources define `getEloquentQuery()` with `OwnerUiScope::apply(..., includeGlobal: false)` (verified: Follow, Subscription, Reaction, Response, Bookmark, BookmarkCollection, Reminder). Exemplary — the reference adapter for scoping alongside filament-communications.

## Database Findings
- 11 migrations with a dedicated owner-uniqueness scoping migration — evidence of careful multitenancy retrofit. PASS.
- Polymorphic columns (`actor/subject` morphs) need composite indexes `(subject_type, subject_id, status)` + `(actor_type, actor_id)` per table — verify during test pass (high-fan-out tables: follows, reactions, responses).
- Counters table (`engagement_counters`) keyed per subject + owner (per uniqueness migration) — correct anti-leak shape. PASS.

## Model / Domain Findings
- 8 primitives in one package is broad but each is a 1-table concept with its own status enum — acceptable generic-social-graph scope. Do NOT split (splitting follows/bookmarks/reactions into micro-packages would multiply the exact integration overhead this audit criticizes elsewhere).
- `Share` model + `ShareUrlGenerator` + `ShareStatus` (created/completed/expired/failed/revoked events) is really link-tracking, adjacent to affiliates' link tracking — document the split (engagement shares = social distribution; affiliate links = compensated attribution) so implementers don't build attribution on shares.
- `BookmarkCollection`/`BookmarkCollectionItem` two-level hierarchy is sufficient; no deeper nesting needed.

## Security Findings
1. Severity: Low. Location: Filament actions (`FollowAction`, `BookmarkAction`, `ReactAction`, `RespondAction`, `SubscribeAction`, `SetReminderAction`) + relation managers. Actions resolve actor/subject from table context — confirm `OwnerWriteGuard`-equivalent or manager-internal owner assertion on every action (manager currently takes `mixed` — after A1 typing, the guard moves to the boundary; until then verify each action re-validates IDs server-side). Test requirement, no defect proven. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `MatchSubscriptionsOnEventOccurrencePublished` listener re-enters owner context from the event model (`OwnerContext::withOwner($owner, ...)`) — correct; verify the derived `$owner` can't be spoofed by a forged event payload (events are server-dispatched, fine). Test requirement, no defect proven. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Performance Findings
1. Severity: Medium. Location: `MatchSubscriptionsCommand` (iterates subscriptions × new occurrence) + `ReconcileEngagementCountersCommand` (full recount). Both are batch/off-peak — confirm chunked iteration (`chunkById`) + queued dispatch per match (not inline notification sends). After the `OwnerBatchRunner` rewrite (finding C1), batching comes free. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: counter reads on hot subjects — serve from `engagement_counters`, reconcile on schedule; already the design. Verify Filament overview widget caches per owner. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Testing Findings
- Severity: High. Zero tests despite 10 factories + 3 commands + policy/state resolvers. Minimum Pest suite: manager matrix per primitive (create/duplicate-idempotent/remove), mute/unmute, collection add/remove, subscription match on fake event publication, reminder due→send, counter-after-write, cross-tenant isolation for all 10 models (factories make this cheap), policy resolver allow/deny. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `events` | `EventEngagementManager` contract, occurrence-published listener | Medium — half-bridge divergence risk | Complete or remove the bridge (A3) |
| `communications` | reminder/notification delivery | Low — future delivery bridge | Additive bridge when pursued |
| `filament-engagement` | manager + all models | Medium — signature changes | Update 8 actions after A1 typing |
| none (consumers) | shares vs affiliate links | Low — conceptual overlap | Docs cross-link only |

## Recommended Refactor Plan (ordered steps)
1. Type `DefaultEngagementManager` boundaries (`CanInteract` actor + per-primitive `*able` subjects); update 8 Filament actions.
2. Collapse trait internals behind two shared helpers (keep 14 public names).
3. Rewrite batch commands on `OwnerBatchRunner`; delete sniff blocks.
4. Decide the events bridge (complete or remove) with events team.
5. Bridge reminder delivery through communications (or document split).
6. Verify polymorphic composite indexes + counter-after-write tests.
7. Write Pest suite (factories already exist — lowest test cost in the set).

## Files Likely to Change
- `packages/engagement/src/Services/DefaultEngagementManager.php`, `Contracts/CanInteract.php` (actor bound)
- `packages/engagement/src/Traits/*.php` (14 → delegate to 2 internals), `Support/ModelResolver.php`
- `packages/engagement/src/Console/Commands/MatchSubscriptionsCommand.php`, `SendDueRemindersCommand.php`
- `packages/engagement/src/Integrations/Events/EngagementEventEngagementManager.php`
- `packages/filament-engagement/src/Actions/*.php` (8 call-site updates)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)
- Hand-rolled owner-sniff/strip/re-enter blocks in `MatchSubscriptionsCommand.php:52-77` (replaced by `OwnerBatchRunner`; verified seating/ticketing already use it — shared primitive exists)
- Duplicated owner/model-resolution bodies inside the 14 traits (moved to shared internals; public trait names kept)
- Either the `MatchSubscriptionsOnEventOccurrencePublished` listener or the events-side `Interested` shadow path (A3 decision — one side goes; half-bridge not preserved)

## Final Recommended Architecture
- `engagement` = typed generic social-graph primitives (follows/bookmarks/responses/reactions/subscriptions/reminders/shares + counters), fully owner-scoped, batched via `OwnerBatchRunner`, delivered via `communications`. Events integration is either a complete bidirectional bridge or nothing — no half-listeners.
