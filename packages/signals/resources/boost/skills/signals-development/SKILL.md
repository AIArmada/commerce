---
name: signals-development
description: Develop and integrate signals using owner-scoped Laravel Dune feature APIs.
---

# Signals development

- Use owner-scoped feature APIs for mutations: Ingestion owns properties and interaction rules, Reporting owns goals, segments and saved reports, and Alerts owns alert rules and read state.
- Resolve the owner context before reading or writing analytics. A report filter does not authorize access.
- Use `AIArmada\Signals\Features\Reporting\Api\DashboardRead` for dashboard summaries, trends and registered report summaries. Pass a tracked-property ID, not a model.
- Use `AIArmada\Signals\Features\Reporting\Api\SegmentOptions` for active segment choices.
- Use `AIArmada\Signals\Features\Ingestion\Api\SignalIngestion` with a tracked-property ID and explicit trust flag for application-owned event producers. The result is an immutable receipt.
- Browser ingestion is non-financial; trusted HTTP outcomes require a signed server request. Validate untrusted inputs at the application boundary before invoking direct ingestion.
- Keep external transports behind package contracts and drivers. Do not write another feature's tables directly.
