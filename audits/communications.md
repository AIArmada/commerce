# Communications Audit

## Packages Reviewed (bullets)
- `packages/communications` (`aiarmada/communications`) — comms domain: batches/threads/communications, recipients/deliveries/attempts/events, templates/versions, preferences/suppressions, attachments/references/tracking, destinations, notification inboxes, webhooks
- `packages/filament-communications` (`aiarmada/filament-communications`) — Filament v5 admin: 7 resources (batch, delivery, preference, communication, suppression, template, thread), timeline/communications/deliveries relation managers, delivery-status widget

## Overall Assessment (quality, health, risks, refactor size)
- Quality: high relative to set. Best-structured package audited: 15 contracts with Null-object defaults, 10 spatie-data DTOs, ADRs (`docs/adr/001–005`), `FakeCommunicationManager` test double, `commerce_json_column_type()` + `commerce_schema_create_if_missing()` in migrations, correct `hash_equals` webhook verification, `CarbonImmutable` throughout.
- Health: large but coherent (17 models, 17 migrations, 31 actions, 6 console commands). Zero tests despite shipping a fake + ADRs that describe test strategy. Main drag is aggregate breadth (17 tables for messaging) and 9 near-identical Null resolvers.
- Risks: (1) `AutoCaptureNotificationListener` + `RecordNativeNotificationSending/Sent` listeners auto-record every Laravel notification — high blast radius if misconfigured; (2) `ProcessWebhookEventJob` replays provider events into delivery state — idempotency depends on registrar/normalizer wiring done outside the package; (3) payload redaction (`PayloadRedactorService`, `DestinationProtectorService`) is opt-in plumbing — a missed call site leaks PII into `metadata`/`snapshot` JSON.
- Refactor size: S–M (1–3 days). Mostly verification + collapsing Null boilerplate + scoping one resource; no schema redesign.

## Migration Impact
**Migration Required: NO**
- Tables/columns: no changes. 17 migrations all use `uuid('id')->primary()` + configurable JSON type; `communications.php` defines `json_column_type`.
- Indexes/constraints: no FK constraints/cascades (verified) — compliant. No constraint work.
- Data migration: none. Null-resolver consolidation and listener hardening are code/config-only.

## Package Responsibilities
- Core owns: communication aggregate lifecycle (`CreateCommunicationAction`, `PlanCommunicationDeliveriesAction`, `TransitionDeliveryAction`, `StartDeliveryAttemptAction`, `CompleteDeliveryAttemptAction`, `RecalculateCommunicationStatusAction`, `CancelCommunicationAction`, `RetryCommunicationDeliveryAction`, `DeleteCommunicationAggregateAction`), eligibility/suppression/consent/quiet-hours/rate-limit pipeline (`ResolveCommunicationEligibilityAction`, resolvers + Null defaults), rendering (`RenderCommunicationContentAction`), threading (`ResolveCommunicationThreadAction`), inbound (`ReceiveInboundCommunicationAction`), provider events (`RecordProviderEventAction`, `ApplyProviderEventAction`, `ProcessWebhookEventJob`, `Webhooks/Normalizers/*`, `Webhooks/Registrars/*`), tracking (`CreateTrackingTokenAction`, `RecordTrackingInteractionAction`), templates (`PublishTemplateAction`), batches/due-dispatch (`CreateCommunicationBatchAction`, `DispatchDueCommunicationsCommand`, `ExpireCommunicationsCommand`, `ReconcileCommunicationStatusCommand`), pruning/redaction (`PruneCommunicationDataAction/Command`, `RedactCommunicationPayloadAction`), inbox (`NotificationInboxService`, `DispatchInboxNotificationAction`, `PruneNotificationInboxesCommand`, Livewire `InboxIndex`), Laravel-notification capture (`Listeners/AutoCaptureNotificationListener.php`, `RecordNativeNotificationSending.php`, `RecordNativeNotificationSent.php`, `Support/AutoCaptureState.php`).
- Filament adapter owns: 7 resources + view pages, 3 relation managers, `DeliveryStatusOverviewWidget`, plugin/provider.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)
1. Severity: Low. Location: `packages/communications/src/Listeners/AutoCaptureNotificationListener.php`, `Support/AutoCaptureState.php`, `Services/CommunicationRecorderService.php` (`sending_at/sent_at/failed_at` setters). Problem: auto-capture of native Laravel notifications into the comms aggregate is global once enabled — an empty `auto_capture_allowlist` captures every notification family. Re-check 2026-09-07: blast radius overstated as filed — master switch `communications.features.auto_capture` already defaults OFF (`:34,86`), and both `auto_capture_allowlist` + `auto_capture_denylist` already exist (`isAllowedNotification():117-133`); demoted Medium→Low. Remaining gap: empty-allowlist-means-capture-all plus no `NotificationFamily`/`NotificationTrigger` gating. Why It Matters: enabling the flag without an allowlist silently turns `notification_inboxes.data` JSON into a cross-package PII sink. Recommended Fix: keep auto-capture but default to allowlist-gated for unknown notification families (`NotificationFamily`/`NotificationTrigger` enums exist — use them as allowlist); require explicit per-notification opt-in via `BaseCommunicationNotification` subclass or config allowlist; document in `docs/04-usage.md`. Breaking Change: NO (config default change only; note as behavior change in docs). Affected Packages: all packages sending Laravel notifications (`events` welcome/ticket notifications, `ticketing` pass notifications, `engagement` reminders). Required Dependent Changes: those packages' notifications must extend `BaseCommunicationNotification` or register in allowlist to keep being captured. Migration Required: NO.
2. Severity: Medium. Location: `packages/communications/src/Services/NullConsentResolver.php`, `NullPreferenceResolver.php`, `NullQuietHoursResolver.php`, `NullRateLimiter.php`, `NullSuppressionResolver.php`, `NullContentRenderer.php`, `NullDestinationResolver.php`, `NullRecipientSnapshotResolver.php`, `NullCommunicationAuditRecorder.php` (9 files — all verified present) + `Webhooks/Normalizers/NullProviderEventNormalizer.php` (verified — 10 total). Problem: 10 pass-through Nulls with identical shape — each new contract requires a new Null file. Why It Matters: boilerplate tax on every extension; reviewers must open 10 files to confirm "does nothing" semantics. Recommended Fix: keep the Null-object pattern (it is the right call for standalone install) but collapse the five "always-allow" resolvers (consent/preference/quiet-hours/rate-limit/suppression) into one `PermissiveEligibilityResolver` implementing all five interfaces: delete the 5 files, add `PermissiveEligibilityResolver`, update `CommunicationsServiceProvider` bindings (no deprecated aliases, no BC shims — update all internal consumers in the same pass); keep distinct Nulls only where "do nothing" differs semantically (audit recorder, content renderer, destination resolver). Breaking Change: YES (container bindings change for anyone overriding individual Nulls). Affected Packages: any app overriding these bindings (none in-repo). Required Dependent Changes: update service-provider overrides to the single class. Migration Required: NO.
3. Severity: Low. Location: `packages/communications/src/Webhooks/Registrars/ProviderWebhookRegistrarService.php`, `Webhooks/Contracts/ProviderWebhookRegistrar.php`, `Webhooks/WebhookOwnerResolver.php`, `Contracts/WebhookOwnerResolver.php`. Problem: two owner-resolver-adjacent classes (`Webhooks/WebhookOwnerResolver.php` concrete + `Contracts/WebhookOwnerResolver.php` contract) plus registrar service — indirection is fine but naming collision invites wrong imports. Recommended Fix: rename concrete to `ConfigWebhookOwnerResolver` to match the `*Service`/`Null*` naming scheme. Breaking Change: YES (class rename). Affected: `Http/Controllers/WebhookController.php`, `Jobs/ProcessWebhookEventJob.php`. Required Dependent Changes: update imports. Migration Required: NO.

## Code Quality Findings (same finding format)
1. Severity: Low. Location: `packages/communications/src/Models/Communication.php:228` (`$communication->references()->each(fn (...) => $r->delete())`). Problem: N+1 individual deletes in model code instead of a single query (`references()->delete()` / chunked delete). Why It Matters: aggregate delete cost scales with reference count; fires N model events unintentionally. Recommended Fix: replace with `$communication->references()->delete()` if model events are not needed, or `chunkById` + delete if they are; move to `DeleteCommunicationAggregateAction` (which already exists — route all deletes through it). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `packages/communications/src/Testing/FakeCommunicationManager.php`. Problem: GOOD — a fake exists, but with zero tests it is unverified dead weight today. Recommended Fix: keep (do not delete) and cover it with the first Pest tests (assert fake records dispatches). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Laravel-Specific Findings
- PHP 8.4, `declare(strict_types=1)`, spatie-data DTOs, spatie-package-tools provider: PASS.
- UUID PKs, `getTable()` from config (spot-checked pattern consistent), no FK constraints/cascades: PASS.
- `json_column_type` defined and used in every migration sampled: PASS (exemplary — this is the reference implementation for other packages).
- Idempotent-safe migrations via `commerce_schema_create_if_missing()`: PASS (only package in set doing this consistently).
- `CarbonImmutable`: PASS. No `SoftDeletes`: PASS. `HasOwner` on 17/17 models per CONTEXT (grep showed HasOwner hits across all model files): PASS.
- Jobs: `ProcessWebhookEventJob` correctly uses `OwnerContext::withOwner(OwnerContext::fromTypeAndId(...))` — exemplary tenant propagation into queue workers. PASS.
- Commands iterate owners via `OwnerContext::fromTypeAndId` + `withOwner` per batch (`DispatchDue`, `Expire`, `Prune`, `Reconcile`, `ReplayWebhookEvents`): PASS.

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)
- Thin-adapter: PASS. All 7 resources are list/view-only pages (no create/edit forms duplicating domain writes) + `OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)` on every resource verified (`CommunicationBatchResource:40`, `CommunicationSuppressionResource:41`, `CommunicationThreadResource:40`, `CommunicationTemplateResource:41`, `CommunicationPreferenceResource:40`, `CommunicationResource:42`, `CommunicationDeliveryResource:40`). Exemplary.
- Domain leak: none found. Relation managers (`CommunicationTimelineRelationManager`, `CommunicationsRelationManager`, `DeliveriesRelationManager`) are read views. PASS.
- Dependency direction: CORRECT (requires `aiarmada/communications`; core has no Filament dep). PASS.
- Navigation: PASS. `getNavigationGroup(): ?string` reads `config('filament-communications.navigation.group')`; config has nested `navigation.group`. No static props.
- Gap (Low): no create/retry actions in admin for failed deliveries (`RetryCommunicationDeliveryAction` exists in core but has no Filament entry point) — add a Filament table action delegating to the core action with `OwnerWriteGuard`. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Database Findings
- 17 tables is a lot but each maps to a distinct aggregate concern (batch/thread/communication/recipient/content/delivery/attempt/event + template/version + preference/suppression + attachment/reference/tracking/destination/inbox). No merge recommended — the aggregate boundaries are documented in ADRs 001/003.
- `000017` numbering jumps to `000019` (`..._000019_create_communication_destinations_table.php`) — a missing `000018` suggests a removed/renamed migration. Harmless (timestamp-prefixed `2000_01_01` dummies run in order), but confirm no `000018` file is referenced in docs; if truly dead, no action needed.
- Index audit recommended on `(status, scheduled_at)`, `(delivery_id, created_at)` for attempts/events, `(token)` unique on tracking tokens — verify during test-writing.

## Model / Domain Findings
- 13 enums give strong typing (`CommunicationStatus`, `DeliveryStatus`, `ThreadStatus`, `TemplateStatus`, `SuppressionReason`, `RecipientRole`, `CommunicationCategory/Direction/Priority`, `NotificationFamily/Priority/Trigger`, `CommunicationEventSource`). No enum/state duplication (unlike affiliates) — states live in status columns transitioned by actions. PASS.
- `CommunicationDestination` (table `000019`) + `DestinationProtectorService` + `PayloadRedactorService` + ADR-005 show PII handling was designed in — but redaction is call-site dependent. Add a `RedactCommunicationPayloadAction` invocation audit: every path writing `snapshot`/`metadata`/`data` JSON must pass through redactor (write the grep as a CI check or test).
- `NotificationInbox` + `HasInbox`/`HasCommunicationContext` traits give recipient-side reads — good separation from sender-side aggregate.

## Security Findings
1. Severity: Low (well-handled). Location: `Http/Middleware/VerifyWebhookSignature.php:26-42`. Problem: none found — HMAC-SHA256 over raw body with `hash_equals`, 401 on missing/unconfigured secret (re-check 2026-09-07: lines 23-41 confirmed). Remaining hardening: bind allowed providers (route `{provider}` allowlist via registrar), add replay protection (timestamp tolerance + idempotency key on `CommunicationEvent`), rate-limit the webhook route group. Why It Matters: replay/enumeration on inbound provider webhooks. Recommended Fix: as listed; no new columns (idempotency key can reuse `tracking_tokens`/`events` payload hash). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO (no new columns).
2. Severity: Low. Location: `communication_destinations` + `destination` JSON payloads. Problem: unverified whether `DestinationProtectorService` (`Support/DestinationProtectorService.php`) encrypts channel addresses at rest (notification inbox `data` uses JSON, not `encrypted:array`). Why It Matters: PII at rest. Recommended Fix: verify whether addresses need `encrypted:json` casts on `CommunicationDestination`; flagged as verification item. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Performance Findings
1. Severity: Medium. Location: `DispatchDueCommunicationsCommand` + `PlanCommunicationDeliveriesAction` + `ReconcileCommunicationStatusCommand`. Problem: due-dispatch scans `scheduled_at <= now` across tenant batches; reconcile recomputes aggregate status. Why It Matters: dispatch latency at volume. Recommended Fix: verify composite index `(status, scheduled_at)` and chunked owner-iteration (pattern already used); add `queue` dispatch per batch rather than inline send. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO (index-only if missing).
2. Severity: Low. Location: `PruneCommunicationDataAction/Command` retention deletes. Problem: unverified whether deletes are chunked. Why It Matters: long locks on large prunes. Recommended Fix: confirm chunked deletes (not one big `delete()`) to avoid long locks. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Testing Findings
- Severity: High. Location: `packages/communications/`, `packages/filament-communications/` — zero test files (re-check 2026-09-07: no `tests/` dir in either package; `Testing/FakeCommunicationManager.php` ships unverified). Problem: eligibility pipeline, delivery state machine, webhook idempotency, and PII redaction ship without regression coverage. Why It Matters: delivery/redaction correctness without a safety net — major maintainability risk, kept at High per rubric. Recommended Fix: minimum Pest suite: eligibility pipeline matrix (suppressed/unsubscribed/quiet-hours/rate-limited), `PlanCommunicationDeliveriesAction` + `TransitionDeliveryAction` state machine, webhook → `ProcessWebhookEventJob` → `ApplyProviderEventAction` with replayed duplicate (idempotency), redaction test (PII scrubbed from stored JSON), cross-tenant isolation per model, `FakeCommunicationManager` contract test. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `events` | `EventNotificationDispatcher`, notification batches/deliveries | Medium — events builds its own notification batch tables AND dispatches via comms concepts | Long-term: route event notifications through comms; short-term: no change (see events audit) |
| `ticketing` | pass/transfer notifications | Low — uses own Laravel notifications | Opt into `BaseCommunicationNotification` to get capture+recording |
| `engagement` | reminder notifications | Low — same as above | Same opt-in |
| `contacting` (suggested) | recipient resolution | Low — `DestinationResolver` seam | Implement `DestinationResolver` for contact models when needed |
| `filament-communications` | all core models | Low — read-only admin | Add retry-action delegating to core |

## Recommended Refactor Plan (ordered steps)
1. Flip auto-capture to allowlist-default with `NotificationFamily` gating; document.
2. Collapse 5 permissive Null resolvers into `PermissiveEligibilityResolver`; update provider bindings + consumers.
3. Rename `Webhooks/WebhookOwnerResolver.php` → `ConfigWebhookOwnerResolver`.
4. Route `Communication::references` deletes through `DeleteCommunicationAggregateAction` (single-query).
5. Add Filament retry action for failed deliveries (delegates to core, guarded).
6. Verify indexes on due-dispatch/tracking hot paths; confirm chunked pruning.
7. Write Pest suite (pipeline matrix, webhook idempotency, redaction, isolation, fake contract).

## Files Likely to Change
- `packages/communications/src/Listeners/AutoCaptureNotificationListener.php`, `Support/AutoCaptureState.php`, config allowlist, `docs/04-usage.md`
- `packages/communications/src/Services/Null*.php` (5 deleted, 1 added), `CommunicationsServiceProvider.php`
- `packages/communications/src/Webhooks/WebhookOwnerResolver.php` (rename)
- `packages/communications/src/Models/Communication.php`, `Actions/DeleteCommunicationAggregateAction.php`
- `packages/filament-communications/src/Resources/CommunicationDeliveryResource.php` (retry action)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)
- `packages/communications/src/Services/NullConsentResolver.php` — merged into `PermissiveEligibilityResolver` (verified single-purpose pass-through; update provider binding)
- `packages/communications/src/Services/NullPreferenceResolver.php` — same
- `packages/communications/src/Services/NullQuietHoursResolver.php` — same
- `packages/communications/src/Services/NullRateLimiter.php` — same
- `packages/communications/src/Services/NullSuppressionResolver.php` — same
- (Keep `NullContentRenderer`, `NullDestinationResolver`, `NullRecipientSnapshotResolver`, `NullCommunicationAuditRecorder`, `NullProviderEventNormalizer` — semantically distinct "do nothing" defaults.)

## Final Recommended Architecture
- Keep the 15-contract + Null-default architecture; it is the correct standalone-vs-integrated seam. Reduce Null count from 10→6, gate auto-capture by family allowlist, and make redaction call-site coverage testable. Filament stays read-only + guarded actions.
