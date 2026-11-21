---
name: laravel-dune-convert
description: Move misplaced Commerce domain logic into its owning package or consolidate duplicated package responsibilities through verified slices.
---

# Laravel Dune — Correct Package Ownership

Read `CONTEXT-MAP.md`, the owning `packages/<pkg>/CONTEXT.md`, its canonical docs, and matching `.ai/rules/index.md` entries before discovery or edits. Use graph tools for code discovery. Follow the root [Dune guideline](../../guidelines/laravel-dune.blade.php) and [verification rule](../../rules/verification-and-hygiene.md).

1. Inventory callers, durable state, routes, authorization, owner semantics, and side effects for the requested capability. Identify the owning core package and affected adapters. For a package-wide conversion, use [laravel-dune-package](../laravel-dune-package/SKILL.md) to inventory the public Composer and Laravel integration surfaces.
2. Capture behavior with affected tests before moving code. Keep the change to one verifiable capability at a time.
3. Move domain orchestration out of demo or Filament code into the core package. Move shared infrastructure into `commerce-support` only when multiple packages benefit.
4. Migrate callers to the supported package API, then remove superseded paths in scope. Preserve standalone installation and optional integration behavior.
5. Verify behavior, tenancy, transaction failure, and relevant retries. Update existing docs for intentional breaking changes and their migration path.

Complete when in-scope callers use the owning package, duplicate paths are removed, and affected package checks pass. Use existing Composer providers and package layout throughout.
