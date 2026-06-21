# ADR 001: Package Boundary and Non-Goals

**Status:** Accepted

**Context:** The communications package must record outbound, inbound, and internal communication facts without becoming a monolithic engagement platform. Laravel Notifications and Queue already own message dispatch and async execution. Campaign segmentation, helpdesk workflow, CRM pipelines, and provider billing reconciliation are separate domains.

**Decision:**

1. The package owns durable communication facts: messages, recipients, rendered content snapshots, deliveries, attempts, provider callbacks, and aggregate state.
2. Laravel Notifications remains the message dispatch API — `$user->notify()` and `Notification::send()` continue to work unchanged.
3. Laravel Queue remains the execution infrastructure — no second jobs table, worker, retry engine, or Horizon replacement.
4. Campaign segmentation, A/B testing, conversion attribution, lead scoring, and funnel logic are out of scope.
5. Support ticket workflow, SLA enforcement, and agent assignment are out of scope.
6. Provider billing reconciliation beyond storing per-delivery cost facts is out of scope.
7. Hard-coded vendor integrations (Twilio, Postmark, SES, etc.) are out of scope — provider adapters register through tagged registrars.
8. Database notification records remain a user-facing in-app channel, not the communication ledger.

**Consequences:** The package stays focused on recording and operating communication facts. Integrations with campaigns, orders, events, and other domains happen through polymorphic references, not foreign keys. Provider adapters live in separate packages and register through contracts.
