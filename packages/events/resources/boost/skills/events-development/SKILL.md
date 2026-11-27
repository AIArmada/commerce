---
name: events-development
description: Build and test integrations using aiarmada/events (AIArmada\Events) — the Modular Dune event platform with 9 features, scalar-ID contracts, drivers, config-driven models, and Testbench/Pest verification.
---

# Events Development

## When to use this skill

Use this skill when installing, configuring, extending, or testing
`aiarmada/events` inside a Laravel application, or when changing package code
under `packages/events/`.

## Architecture you must respect

- **9 owned features**: `Catalog`, `Venues`, `Taxonomy`, `Registrations`,
  `Attendance`, `Commerce`, `Changes`, `Governance`, `Search`. Each feature has
  a `feature.json` manifest listing the `table:*` resources it owns and the
  `Actions`/`Persistence` classes allowed to write them.
- **Single-writer ownership**: durable writes live in the owning feature's
  `Actions`/`Persistence` classes. Cross-feature calls go through the feature's
  `Api` interface (`Features\<Feature>\Api\`). Verify with
  `composer arch:check` (AST boundaries + Larastan OwnedWriteRule); keep
  `tools/architecture.json` at `"exceptions": []`.
- **Pure boundaries**: `src/Contracts` and `src/Data` are `interface`, `enum`,
  and `final readonly class` only — zero Eloquent, zero `Illuminate\Http`, zero
  `spatie/laravel-data`. Public contracts take and return scalar IDs.
- **Drivers**: external SDK/package calls live in `src/Drivers` behind
  `src/Contracts` interfaces; bind overrides in a service provider. Never
  import a concrete driver from `Platform` or `Features` code.
- **Platform core**: `Platform\ModelResolver` (`eventClass()`,
  `registrationClass()`, … 23 keys from `events.models.*`),
  `Platform\EventWriteGuard`, `Platform\FeatureCatalog`,
  `Platform\ExtensionRegistry` (slots `events.cascade-delete`,
  `events.clone-scope`), and the ownership traits.
- **Zero legacy**: no `Events` facade, no top-level legacy namespaces under
  `AIArmada\Events`, no backward-compatibility shims. Update callers directly.

## Conventions

- Read package configuration via `config('events.*')`.
- Resolve models through `AIArmada\Events\Platform\ModelResolver` instead of
  importing concrete model classes when the host may override
  `events.models.*`.
- Type-hint interfaces from `AIArmada\Events\Contracts\` and feature `Api`
  interfaces so drivers and queries remain swappable in tests.
- Prefer immutable value objects from `AIArmada\Events\Data\` across
  application boundaries.
- Status transitions run through `spatie/laravel-model-states`; use
  `EventLifecycleWorkflow` (scalar target type + ID) when a transition must
  record change logs and dispatch domain events.
- Owner scoping: run event writes in `OwnerContext::withOwner(...)`; catalog
  writes (venues, taxonomy) run in explicit global context
  (`OwnerContext::withOwner(null, …)`). `CatalogApi::describeScope()` and
  `RegistrationsApi::lock()` honor the active owner context by default.
- Record privacy: registration and submission policies allow managers and the
  polymorphic record owner, not unrelated viewers of a public event.
  `requestRefund` and `manageAdmissions` are explicit deny-by-default
  abilities; `EventRegistrationPolicy::requestRefund()` also allows the
  registrant.
- Check-in requires a `check_in_allowed_statuses` registration, valid passes,
  and a check-in-accepting occurrence (explicit or inferred).
- Pass issuance locks the parent scope chain then the registration, aggregates
  all item quantities per ticket type, and skips terminal statuses outside
  `events.lifecycle.registration.pass_issuance_statuses`.
- Mode inheritance is session → occurrence → event for `pricing_mode`,
  `registration_mode`, and nullable `issue_passes_for_free`; explicit `false`
  must survive create, clone, and template flows.

## Testing

- Run package-related Pest/PHPUnit feature tests after any integration change
  (`./vendor/bin/pest --parallel tests/src/Events` from the monorepo root).
- Static analysis: `php vendor/bin/phpstan analyse packages/events/src
  packages/filament-events/src --level=6 --no-progress` from the monorepo root.
- Architecture: `composer --working-dir=packages/events arch:check`.
- Formatting: `./vendor/bin/pint --test` on changed files.

## Documentation

Keep `packages/events/docs/` and this skill in sync when the public API or
config changes. Usage lives in `docs/04-usage.md`; configuration keys in
`docs/03-configuration.md`.
