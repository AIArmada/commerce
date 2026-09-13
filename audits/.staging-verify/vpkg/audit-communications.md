### Prior-audit section
### communications (17 models)
Bugs:
- Tracking token HIGH — `CreateTrackingTokenAction:26-47` discards plaintext (`Str::random(64)`), persists only hash; `getToken()` returns hash-as-bearer; no redemption route.
- Inbound `findOrFail` without guard HIGH — `CreateTrackingTokenAction:26`, `AddCommunicationRecipientAction:25`, `RecordTrackingInteractionAction:26-28` trust global scope.
- `Communication:221-243` + `Delivery:201-208` MEDIUM — `chunkById(100)->each(delete)` ×6 with nested cascades → 1000s queries.
Security:
- Inbound IDs without guard HIGH (above).
- Attachment `disk/path/mime` mass-assignable MEDIUM-verify — `Attachment:47-60` fillable; no in-`src/` `mimes/max` (Filament-only = API bypass). Confirm server validation.
- `DestinationProtectorService:12-32` AES-CBC unauthenticated LOW-verify — confirm or use AEAD.
- Webhook ingress GOOD — HMAC+`hash_equals`+300s+abort-on-missing-secret; `__owner_*` strip + server re-resolve.
Performance: GOOD — queued webhooks/deliveries/notifications; `202` dispatch; configurable idempotency store. Delete amplification (bugs) is the perf cost.

### Prior-audit fix-first rows
| 16 | communications | `Actions/CreateTrackingTokenAction.php:26-47` | Plaintext token discarded, `getToken()` returns hash; no bearer route | HIGH |
| 17 | communications | `AddCommunicationRecipientAction`, `RecordTrackingInteractionAction` | `findOrFail` without `OwnerWriteGuard` | HIGH |
