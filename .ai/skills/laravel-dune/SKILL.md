---
name: laravel-dune
description: Build or evolve Commerce monorepo packages, shared primitives, integrations, and paired Filament adapters.
---

# Laravel Dune — Commerce Package Development

Read `CONTEXT-MAP.md`, the owning `packages/<pkg>/CONTEXT.md`, its canonical docs, and matching `.ai/rules/index.md` entries before discovery or edits. Use graph tools for code discovery. Follow the root [Dune guideline](../../guidelines/laravel-dune.blade.php) and [verification rule](../../rules/verification-and-hygiene.md).

1. Identify the core owner and any paired Filament adapter. State the intended behavior and success criteria before non-trivial edits.
2. Inspect sibling implementations, existing Actions, and `commerce-support` primitives. Confirm installed versions before relying on package APIs.
3. Implement domain changes in the core package and UI adapters in the paired Filament package. Use existing provider, config, migration, namespace, and test conventions.
4. Keep durable mutations in the owning package's supported operations. Revalidate submitted IDs and preserve owner scoping in HTTP, background, and admin entry points.
5. Update existing canonical docs when public behavior/config changes. Run package-scoped proof with `laravel-dune-verify`.

Complete when observable behavior passes affected checks and any remaining verification gaps are stated. This skill develops Composer packages; consumer-app scaffolding is distributed separately by `commerce-support`.

For package layout selection or structural conversion, use [laravel-dune-package](../laravel-dune-package/SKILL.md).
