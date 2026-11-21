---
paths:
  - packages/**
---

# Package-Owned Capabilities

Read the owning package's `CONTEXT.md` and canonical docs first. Use its Composer namespace, service provider, and existing directory conventions.

Core packages own models, state transitions, calculations, persistence, and reusable orchestration. Filament packages own resources, pages, widgets, forms, and admin actions that delegate domain operations to core APIs.

Prefer concrete Actions for orchestration across transactions, side effects, normalization, or entry points. Reuse existing Actions and keep trivial handlers inline. Enforce owner-safe IDs and authorization in the callable write path, not only in forms.

Models use UUIDs, config-backed `getTable()`, typed relations, and application-level cascades in `booted()`. Preserve instance table aliases for self-relations. Follow lifecycle and migration rules from the monorepo contract.

Use existing package APIs for cross-package work. Keep optional dependencies optional and auto-register integrations through established provider/contributor seams. Shared primitives belong in `commerce-support` when multiple packages benefit.

Update existing canonical docs with public behavior or configuration changes. Verify the owning package and any affected paired adapter.
