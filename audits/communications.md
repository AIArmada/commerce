# Communications Audit — DONE (2026-09-11)

## Verdict

The communications pair (communications + filament-communications) is
cleared. Every rated finding is implemented, falsified with source evidence,
or recorded as an explicit deferral. No events, orders, or other
read-only-package files were changed.

## What was done

- **Auto-capture gating (Low):** the feature remains disabled by default;
  unknown notification classes are denied unless explicitly class-allowlisted
  or opted in with both NotificationFamily and NotificationTrigger
  (packages/communications/src/Listeners/AutoCaptureNotificationListener.php:36-56,
  :121-153). The opt-in path is documented in
  packages/communications/docs/04-usage.md:13-37.
- **Eligibility Null consolidation (Medium):**
  PermissiveEligibilityResolver implements all five eligibility interfaces
  at packages/communications/src/Services/PermissiveEligibilityResolver.php:16-85,
  and the provider binds all five contracts to it at
  packages/communications/src/CommunicationsServiceProvider.php:111-118.
  The five old Null classes were deleted; semantically distinct Null
  renderer, destination, recipient, audit, and provider-normalizer classes
  remain. The incompatible resolve method names were made explicit as
  resolveConsent and resolveSuppression, an intentional breaking change
  permitted by the playbook. No in-repo binding overrides were found.
- **Event-reference normalizer (Medium priority):**
  EventReferenceNormalizer accepts models, CommunicationContextData,
  arrays, or type/id pairs
  (packages/communications/src/Support/EventReferenceNormalizer.php:11-84).
  AttachCommunicationReferenceAction is idempotent and
  CommunicationManagerService normalizes event context before notify
  (packages/communications/src/Actions/AttachCommunicationReferenceAction.php:21-54
  and packages/communications/src/Services/CommunicationManagerService.php:31-58,
  :78-150). The events bridge can therefore pass context fully; the existing
  direct attach remains harmless and idempotent.
- **Webhook hardening (Low):** provider routes now require a configured
  provider allowlist, timestamp within tolerance, raw-body HMAC validation,
  queued-job uniqueness/cache idempotency, and route-group rate limiting
  (packages/communications/src/Http/Middleware/VerifyWebhookSignature.php:19-76,
  packages/communications/src/Jobs/ProcessWebhookEventJob.php:20-97,
  packages/communications/config/communications.php:73-89). Provider event
  records reuse the existing uniqueness boundary and payload hash fallback
  at packages/communications/src/Actions/ApplyProviderEventAction.php:101-141.
- **Deletes and pruning:** aggregate deletion is routed through
  DeleteCommunicationAggregateAction with OwnerWriteGuard and a transaction
  (packages/communications/src/Actions/DeleteCommunicationAggregateAction.php:11-24).
  Child model events are preserved while deletions are chunked at
  packages/communications/src/Models/Communication.php:221-240; pruning is
  chunked in both the action and command.
- **Dispatch/index verification:** the communications status/scheduled_at
  composite index is present at
  packages/communications/database/migrations/2000_01_01_000003_create_communications_table.php:42.
  Due dispatch is owner-context aware and chunked at
  packages/communications/src/Console/Commands/DispatchDueCommunicationsCommand.php:56-91.
  Source review falsified the concern that this command sends providers
  inline: it only transitions scheduled records to queued.
- **PII redaction and fake coverage:** request/response/provider/event and
  other stored payloads pass through PayloadRedactor; delivery attempt
  request/response coverage is at
  packages/communications/src/Actions/StartDeliveryAttemptAction.php:12-34
  and CompleteDeliveryAttemptAction.php:11-31. FakeCommunicationManager
  remains and is covered by CommunicationsFakeTest.
- **Filament retry:** failed deliveries expose a guarded retry action that
  revalidates ownership and delegates to core at
  packages/filament-communications/src/Resources/CommunicationDeliveryResource.php:90-110.
- **Other database verification:** attempt/event delivery indexes and the
  tracking-token hash uniqueness index are present at migrations 000007,
  000008, and 000015. Stale provider migration references 000017/000018
  were removed; no corresponding migration files existed.

## Residual notes

- No new runtime data columns are required for webhook idempotency or replay
  protection.
- The delivery table already stores protected destination ciphertext,
  destination hash, and destination hint.

## Audit deviations

- CommunicationDestination.address is intentionally still a scalar address
  column, not an encrypted:json cast. Source verification found that the
  resolver protects values before delivery persistence
  (packages/communications/src/Services/CommunicationDestinationResolver.php:76-84),
  while encrypting the source-of-truth address would require a type/length
  migration, key/backfill policy, and deployment gate. This is an explicit
  follow-up decision, documented in the communications usage docs, not an
  unverified claim of table-level encryption.
- Queued per-batch provider dispatch is explicitly deferred: the current
  command has no provider-send worker/action and its verified side effect is
  only scheduled-to-queued transition at
  packages/communications/src/Console/Commands/DispatchDueCommunicationsCommand.php:83-91.
  The hot-path composite index and chunking are implemented.
- The events bridge's direct reference attachment at
  packages/events/src/Jobs/DispatchEventNotificationDelivery.php:198-211
  remains read-only; the new manager normalizer makes it idempotent.
- No external application overrides the deleted eligibility Null bindings
  inside this repository. External consumers must adopt the new shared
  binding and explicit method names as part of the intentional breaking
  change.

## Verification

- Communications Area: 265 passed, 1,202 assertions.
- FilamentCommunications Area: 41 passed, 67 assertions.
- EventReference focused regression: 4 passed, 14 assertions.
- Actions/redaction focused regression: 14 passed, 35 assertions.
- Communications fake focused coverage: included in the Area suite and
  passed.
- All commands used Pest --parallel; no full-suite run was performed.

If the explicit encryption or queued-provider decisions become required by
deployment policy, re-open them as scoped follow-up findings.
