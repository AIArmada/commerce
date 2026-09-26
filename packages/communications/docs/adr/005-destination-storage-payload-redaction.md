# ADR 005: Sensitive Destination Storage, Payload Redaction, and Retention

**Status:** Accepted — Decision 2 superseded (see Corrections)

**Context:** Communication records contain personally identifiable information (email addresses, phone numbers, names) and provider request/response payloads that may include tokens, message bodies, and headers. These must be protected at rest, masked in operational UIs, and safely retained or redacted per policy.

**Decision:**

1. **CommunicationDestination** — optional first-class rows for recipient channel addresses (`communication_destinations`). Used by the default destination resolver before notifiable routing. Delivery records still store protected destination fields rather than relying on live destination rows for history.
2. **DestinationProtectorService** — AES-256-CBC encryption using `app.key` for the destination value. Three output fields on deliveries:
   - `destination_ciphertext` — encrypted value (stored at rest).
   - `destination_hash` — HMAC-SHA256 with owner-aware key for exact lookup and suppression checks.
   - `destination_hint` — masked display value (e.g., `u***@e***.com`).
3. **PayloadRedactorService** — scrubs known sensitive keys (`password`, `secret`, `token`, `authorization`, `api_key`, `private_key`) from request/response payloads before persistence. Configurable redaction patterns and replacement string.
4. **No provider secrets in DB** — credentials, bearer tokens, signatures, and unredacted authorization headers must never be stored in communication tables. Provider packages own secret management.
5. **Content snapshots are historical facts** — once a delivery enters `Sending` status, the associated `CommunicationContent` is immutable. Template edits after sending must not retroactively alter the historical snapshot.
6. **Retention commands** — `communications:prune` removes records older than a configurable retention period. `communications:redact` replaces sensitive payload values while preserving operational structure. Both support `--owner`, `--before`, and `--dry-run` filters.
7. **Filament destination display** — masked `destination_hint` by default. Full destination reveal requires explicit authorization and is logged. Raw payload viewers require `viewSensitivePayload` permission.
8. **No soft deletes** — `PruneCommunicationDataAction` hard-deletes eligible records after legal retention review. `DeleteCommunicationAggregateAction` cascades deletion through all child records in application logic (no DB cascades).

**Consequences:** Apps can store resolvable destinations separately from notifiable models. Sensitive destinations are encrypted at rest and masked in UIs. Provider payloads are redacted before persistence. Historical content snapshots remain accurate. Retention commands use explicit owner-scoped iteration and dry-run safety. All deletion is hard-delete with application-level cascade.

## Corrections

The original text above is preserved as written. Three details drifted after this ADR was accepted:

1. **Decision 2 — encryption scheme (superseded).** This ADR specifies *AES-256-CBC using `app.key`*, which is unauthenticated. `DestinationProtectorService` now uses **Laravel authenticated encryption** (`Crypt::encryptString()`, i.e. AES-256-GCM). Ciphertext written by the old implementation **cannot be decrypted** — re-protect affected destinations after upgrading. See the Breaking changes section of `../../docs/01-overview.md`.
2. **Decision 6 — `communications:redact` does not exist.** The package ships six commands: `communications:dispatch-due`, `communications:expire`, `communications:prune`, `communications:prune-inboxes`, `communications:reconcile`, `communications:replay-webhooks`. Payload redaction is available as the `RedactCommunicationPayloadAction` action, not as an artisan command.
3. **Decision 7 — `viewSensitivePayload` is not referenced in code.** Masked `destination_hint` by default is accurate and enforced. The named permission is not currently checked anywhere, so treat "full destination reveal requires explicit authorization" as the intent rather than the current behaviour.

Decisions 1, 3, 4, 5, and 8 remain accurate and in force. The overall intent — encrypt destinations at rest, mask them in operational UIs, redact payloads before persistence — is unchanged; only the mechanism and the two items above moved.
