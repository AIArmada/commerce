# ADR 003: Data Model, State Precedence, and Out-of-Order Provider Events

**Status:** Accepted

**Context:** The data model spans 15 tables covering batches, threads, communications, recipients, content, deliveries, attempts, events, templates, template versions, preferences, suppressions, attachments, references, and tracking tokens. Provider callbacks may arrive late or out of order (e.g., `delivered` before `sent`). State must not regress.

**Decision:**

1. **Timestamps are facts, status is a summary** — lifecycle timestamp columns (`sent_at`, `delivered_at`, etc.) record the actual event occurrence. The `status` column is a derived summary set by transition rules. Never clear an existing lifecycle timestamp during ordinary transitions.
2. **TransitionDeliveryAction** — a central state machine defines `ALLOWED_TRANSITIONS` from every status to its legal successors. Each transition sets the corresponding `*_at` timestamp only if null (idempotent). The action dispatches the matching domain event (`DeliverySent`, `DeliveryFailed`, etc.).
3. **Out-of-order tolerance** — `TransitionDeliveryAction` only checks that the transition is valid, not that timestamps are chronological. If `delivered` arrives before `sent`, the delivery transitions to `delivered` (which sets `delivered_at`) and later `sent` fills `sent_at` — both timestamps remain visible.
4. **No state regression** — terminal states (`suppressed`, `failed`, `cancelled`, `expired`) have no outgoing transitions. A later provider event cannot un-suppress or un-fail a delivery.
5. **RecalculateCommunicationStatusAction** — inspects all deliveries of a communication to derive the aggregate status. All-deliveries-terminal with at least one success → `Completed`. All failures → `Failed`. Mixed → `PartiallyCompleted`. Non-terminal → `Processing`.
6. **Aggregate status stays synchronized** — after each delivery-level transition, callers should invoke `RecalculateCommunicationStatusAction` to update the parent communication's status and dispatch `CommunicationCompleted`/`CommunicationFailed` as appropriate.

**Consequences:** Provider event ordering is resilient. Timestamps remain complete regardless of arrival order. The transition table is the single source of truth for allowed state changes. Callers must remember to recalculate aggregate status after mass delivery changes.
