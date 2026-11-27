# Communications development

- Use owner-scoped communications and delivery lifecycle actions; use `AIArmada\Communications\Contracts\CommunicationManager` for managed notifications. Never construct a second notification delivery pipeline.
- Aggregate owns message creation, recipients, references, threads, content, attachments and cancellation. The attachment model is `Features\Aggregate\Models\CommunicationAttachment`. Delivery owns attempts, eligibility, provider events and delivery status; use `Features\Delivery\Actions\ApplyProviderEventAction` for provider events.
- Inbox, Suppressions, Templates and Tracking own their respective mutations. Use `Features\Suppressions\Actions\CreateSuppressionAction` and `LiftSuppressionAction`; use `Features\Tracking\Actions\CreateTrackingTokenAction` for tracked links.
- HTTP, queue, CLI and listener adapters are under `EntryPoints/` or the owning feature. Verify webhook signatures over timestamp plus raw request body, enforce the replay window and preserve owner context. Never log raw tokens or decrypted destinations.

- RecipientRouting owns stored destinations and preferences. Use `Features\RecipientRouting\Actions\SaveRecipientDestination` and `SaveRecipientPreference` with persisted recipients; the destination model hides addresses and external identifiers during serialization.

- Webhook provider interfaces live in `Contracts/`; concrete provider registration, normalization and owner resolution adapters live in `Drivers/Webhooks/`.

- Default notification, audit, eligibility, encryption and cache implementations are in `Drivers/Notifications/`, `Drivers/Audit/`, `Drivers/Eligibility/`, `Drivers/Security/` and `Drivers/Cache/`. Bind their package contracts rather than reaching into implementation classes.

- `CommunicationBatch`, `CommunicationThread` and `CommunicationReference` live under `Features/Aggregate/Models/`; use the owning Aggregate actions for mutations.

- `CommunicationContent` and `CommunicationRecipient` are Aggregate-owned models under `Features/Aggregate/Models/`. Use Aggregate actions to create or modify recipient and content records.

- Native notification recording uses the registered notification event listeners and `NativeNotificationCapture`; the manager handles managed notification dispatch only.
