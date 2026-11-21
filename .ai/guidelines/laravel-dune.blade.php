## Laravel Dune for Commerce Package Development

The root `.ai/` guidance applies to development of `packages/*` in this monorepo. Package boundaries are the capability boundaries. The consumer-app Dune distribution under `packages/commerce-support/resources/boost/` is a separate surface.

### Ownership and boundaries

1. Read `CONTEXT-MAP.md`, the owning package's `CONTEXT.md`, its canonical docs, and matching `.ai/rules/index.md` entries before code discovery or edits.
2. Core packages own domain state, calculations, persistence, and reusable Actions. Paired `filament-*` packages own admin adapters and delegate domain operations to the core package.
3. Check `commerce-support` for shared primitives first. Keep foundation code independent of concrete downstream packages; use contracts, tagged contributors, and optional integration registration.
4. Use the existing package namespace, Composer metadata, service provider, routes, config, migrations, and resources. Add only the files the capability needs. Packages must work standalone; optional integrations remain optional.
5. Each durable value has an owning package. Call its supported Actions, services, or contracts for mutations rather than duplicating its write logic in another package. Existing documented model and query APIs remain valid public surfaces.
6. A focused Commerce package is a capability. Keep its existing `src/` layout. Consider a modular layout only for an explicitly scoped package design with independently owned capabilities.

### Persistence and orchestration

- Prefer concrete Actions for reusable orchestration; keep trivial handlers inline. Enforce authorization and owner-safe IDs inside write paths, including jobs and Filament actions.
- Follow the monorepo model contract: UUIDs, config-backed `getTable()` (preserving instance aliases for self-relations), typed relations, immutable date casts, and application-level cascades in `booted()`.
- Follow the database contract: edit owning migrations in place, configurable JSON column type, no foreign-key constraints/cascades or soft deletes.
- Reuse owner scopes, contexts, write guards, cache, and filesystem primitives from `commerce-support`. Keep long-lived workers free of request-leaking static mutable state.
- Keep comments focused on contracts, generics, and non-obvious reasons. Match existing package conventions.

### Proof and tooling

Use graph tools for code discovery and the installed-version documentation tools for framework APIs. Run changed-file Pint, affected-package PHPStan at level 6, and affected Pest tests with `--parallel`, piping output through `tee`. Discover test paths from Composer mappings and the test configuration.

The root does not install the standalone app's feature manifests, Platform runtime, architecture checker, or cache commands. Use existing repository checks; describe any unverified boundary honestly. Update existing canonical package docs with public behavior/config changes.

Use `laravel-dune` for routine Commerce development, `laravel-dune-package` for package design and conversion, `laravel-dune-convert` for moving misplaced responsibilities into their owning package, `laravel-dune-correct` for recurring mistakes, and `laravel-dune-verify` for package-scoped proof.
