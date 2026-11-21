---
paths:
  - packages/*/src/Contracts/**
  - packages/*/src/Contracts.php
  - packages/*/src/Data/**
  - packages/*/src/DTOs/**
  - packages/*/src/Api/**
---

# Package Public Contracts

Read the owning package's documented public API and Composer dependencies before changing a boundary. Public package surfaces may include models, query scopes, services, Actions, events, contracts, and DTOs; preserve the package's established conventions.

Introduce interfaces for real substitution or cross-package integration needs. Prefer the existing domain value objects or Spatie Laravel Data for DTOs. Use typed signatures, generics, immutable dates, and integer minor units with an explicit currency for money.

Parse untrusted payloads at the entry boundary and revalidate inbound foreign IDs for the current owner on writes. Document ownership and global-row semantics where they affect callers.

Update callers and canonical docs in the same change. Verify affected dependents; remove replaced paths instead of adding compatibility aliases.
