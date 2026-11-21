---
paths:
  - packages/commerce-support/**
---

# Shared Package Foundation

Read `packages/commerce-support/CONTEXT.md` before discovery. Check its existing primitives before introducing shared infrastructure.

Keep foundation code independent of concrete downstream domains and Filament adapters. Use contracts, tagged registrars, metadata, or contributor interfaces for optional integrations. Keep providers lean and optional packages optional.

Package code must run in arbitrary consumer applications. Resolve host-specific classes through configured bindings or contracts. Keep Eloquent strictness, global mass-assignment settings, destructive command policy, and the global date factory under host control.

Owner enforcement, contexts, write guards, owner-aware caches and filesystems belong here. Preserve explicit global-context semantics and tenant isolation on every entry point. Verify affected dependents when changing a shared contract.

Assume long-lived workers: scope mutable owner/request state to the request or container lifecycle. Test cleanup after exceptions when modifying scoped context behavior.
