---
name: communications-development
description: Develop and integrate Communications with Laravel Dune ownership, security and canonical APIs.
---

# Communications development

- Use owner-scoped communications and delivery lifecycle actions; use `AIArmada\Communications\Contracts\CommunicationManager` for managed notifications. Never construct a second notification delivery pipeline.
- Aggregate owns message creation, recipients, references, threads, content, attachments and cancellation. The attachment model is `Features\Aggregate\Models\CommunicationAttachment`. Delivery owns attempts, eligibility, provider events and delivery status; use `Features\Delivery\Actions\ApplyProviderEventAction` for provider events.
- Inbox, Suppressions, Templates and Tracking own their respective mutations. Use `Features\Suppressions\Actions\CreateSuppressionAction` and `LiftSuppressionAction`; use `Features\Tracking\Actions\CreateTrackingTokenAction` for tracked links.
- HTTP, queue, CLI and listener adapters are under `EntryPoints/` or the owning feature. Verify webhook signatures over timestamp plus raw request body, enforce the replay window and preserve owner context. Never log raw tokens or decrypted destinations.

- RecipientRouting owns stored destinations and preferences. Use `Features\RecipientRouting\Actions\SaveRecipientDestination` and `SaveRecipientPreference` with persisted recipients; the destination model hides addresses and external identifiers during serialization.

- Webhook provider interfaces live in `Contracts/`; concrete provider registration, normalization and owner resolution adapters live in `Drivers/Webhooks/`.

- Default notification, audit, eligibility, encryption and cache implementations are in `Drivers/Notifications/`, `Drivers/Audit/`, `Drivers/Eligibility/`, `Drivers/Security/` and `Drivers/Cache/`. Bind their package contracts rather than reaching into implementation classes.

- Delete batches and threads through `Features\Aggregate\Actions\DeleteCommunicationBatchAction` and `DeleteCommunicationThreadAction`; never cascade their communications from UI code.
- Thread external ids are unique per owner tuple and separately in the global partition. `ResolveCommunicationThreadAction` writes only the partition implied by `auto_assign_on_create`; it never adopts global rows from an owned context. PostgreSQL `REPEATABLE READ` callers retry when it rethrows `UniqueConstraintViolationException`.
- `CommunicationBatch`, `CommunicationThread` and `CommunicationReference` live under `Features/Aggregate/Models/`; use the owning Aggregate actions for mutations.

- `CommunicationContent` and `CommunicationRecipient` are Aggregate-owned models under `Features/Aggregate/Models/`. Use Aggregate actions to create or modify recipient and content records.

- Native notification recording uses the registered notification event listeners and `NativeNotificationCapture`; the manager handles managed notification dispatch only.

- Managed notification dispatch (`CommunicationManager::notify()` / `Communications::notify()`) accepts `CommunicationContextData` or null and returns the persisted communication ID as a string. Pass event morph type and ID in the context instead of passing an Eloquent model or an untyped array.

- Use `Contracts\CommunicationReferenceWriter` for cross-feature reference attachment. Reference writes are serialized on the owning communication row and return a scalar reference ID.

- Use `AIArmada\Communications\Features\Inbox\Traits\HasInbox` on recipient models. Render template content through `Features\Aggregate\Actions\RenderCommunicationContentAction` using the scalar template ID, not a Templates model.
