---
paths:
  - packages/*/src/**
  - packages/*/routes/**
  - demo/**
---

# Integrations and Application Adapters

External SDKs, HTTP clients, webhook handling, and process adapters belong to the package owning that integration. Keep vendor payload parsing at that boundary; expose the package's supported contracts and data shapes to callers.

Bind implementations in the owning package's provider using existing conventions. Keep optional integrations conditional and independently installable. Reuse shared infrastructure from `commerce-support`.

Keep drivers independently swappable through supported package contracts. Verify provider discovery and driver selection in a booted Laravel test host when changing integration wiring.

`demo/` is a consumer and integration surface. Keep reusable domain behavior in core packages and reusable admin behavior in paired Filament packages. Read both package contexts before cross-boundary changes.

HTTP routes, jobs, webhooks, commands, and Filament handlers enforce the same authorization and owner boundary. Jobs carry explicit owner context rather than depending on ambient web authentication. Test retries and side effects when changing integration orchestration.
