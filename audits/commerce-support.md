# commerce-support Audit — DONE (2026-09-08)

## Verdict

The foundation package (`commerce-support` + `filament-commerce-support`)
has passed full review and implementation. It is a broadly well-designed
foundation — owner-tenancy primitives (`HasOwner`, `OwnerScope`,
`OwnerContext`, `OwnerQuery`, `OwnerWriteGuard`, `OwnerCache`,
`OwnerFilesystem`, …), money standard (`MoneyFormatter`,
`MoneyNormalizer`, `FormatsMoney`), navigation engine, payment/targeting
contracts, and reusable test contracts — and it mostly practices what it
preaches (config-driven tables, `json_column_type` discipline, no FK
constraints/cascades, no soft deletes). Zero rated findings remain.

## What was done

- **Authz models moved** to `AIArmada\Authz\Models` (originals deleted,
  zero old-namespace references) — see `code-fixes-record.md`.
- **Money APIs narrowed to `int`** repo-wide with explicit half-up
  call-site conversions — see `code-fixes-record.md`.
- **Single navigation engine** (`CommerceNavigation` canonical;
  `ManageCommerceNavigation` a thin settings Page) — see
  `code-fixes-record.md`.
- **Octane lifecycle wired** (`flushState()`, registry flush, guarded
  listeners, scoped filament binding) — see `code-fixes-record.md`.
- **Helpers/stubs unified** (shipping call sites on the formatter,
  `commerce_morph_key()`, grouped helpers) — see `code-fixes-record.md`.
- **Dependency-direction guard** added
  (`tests/src/CommerceSupport/Architecture/CommerceSupportArchitectureTest.php`).
- No migrations were ever required by this audit (stub `bigIncrements`
  PKs on shared tables intentionally kept).

## Residual notes

- Octane flush path: the first-Octane-deploy condition is met (production
  runs Octane) with no leakage symptoms reported; soaking continues via
  production telemetry. Symptom class to watch: one request seeing a
  previous request's state, owned by `OwnerContext` lifecycle.
- `ManageCommerceNavigation` feature test still missing;
  `FilamentCommerceSupport` coverage thin.
- `OwnerSignedDownload`/`PublicHttpUrlGuard` call-site audit done:
  exactly two download sites (`AwbController`, `PrintAwbTableAction`),
  both token-bound and owner/user-authorized with 403 on mismatch.
- `currency_symbol()` intentionally retained (`MoneyFormatter::symbol()`
  falls back to it).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
