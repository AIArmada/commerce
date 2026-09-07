# csuite Audit

## Packages Reviewed (bullets)

- `packages/csuite` — metapackage bundle (`aiarmada/commerce`): `composer.json` + `CONTEXT.md` + `README.md` + `docs/` only. No `src/`, no `config/`, no `database/`, no `routes/`, no tests.
- Root test coverage consulted: none package-specific (no code to test); install-shape coverage would live under `tests/` only if a bundle smoke test existed (it does not).

## Overall Assessment (quality, health, risks, refactor size)

csuite contains no runtime code, so there is nothing to migrate and no behavior to break — the audit is about bundle coherence and truth in packaging. The bundle is incoherent as shipped: it requires `filament-authz` without `authz`, includes `jnt`/`docs`/`inventory`/`vouchers`/`cart`/`cashier`/`chip` but omits `signals` (which `growth` needs), `growth` itself, `membership`, `moderation`, and `references`. Its `CONTEXT.md` guardrail ("Owns models, actions, services, events, calculations, and persistence rules") is copy-paste from a domain package and flatly false for a metapackage. Severity peaks at Medium (broken installs for anyone trusting the bundle: filament-authz without its core). Fix is a half-day: correct the require list, fix the context, add a bundle smoke test. No migration.

## Migration Impact

**Migration Required: NO**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| none (no code, no schema) | none | none | Metapackage only; consumer `composer update` pulls corrected dependency set, no data movement |

## Package Responsibilities

- One Composer dependency installing the curated Commerce suite (`aiarmada/commerce`, `type: metapackage`).
- Explicit non-responsibilities (must be documented, currently are not): no runtime code, no config, no migrations, no routes, no models — never edit csuite for behavior; edit the underlying package.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### C1 — Bundle requires `filament-authz` without `authz`
- Severity: Medium
- Location: `packages/csuite/composer.json` (`"aiarmada/filament-authz": "self.version"` present; `"aiarmada/authz"` absent)
- Problem: The Filament adapter cannot function without its core package. A csuite install yields a broken authz admin surface (missing `AIArmada\Authz\*` classes, missing `authz` config/migrations).
- Why It Matters: The bundle's single job is a working dependency set; this is that job failing.
- Recommended Fix: Add `"aiarmada/authz": "self.version"` to `require`.
- Breaking Change: NO (additive)
- Affected Packages: authz, filament-authz
- Required Dependent Changes: none
- Migration Required: NO

### C2 — Bundle omits `signals`/`growth` and the standalone packages without a stated policy
- Severity: Medium
- Location: `packages/csuite/composer.json` (includes cart/cashier/cashier-chip/chip/docs/jnt/inventory/vouchers + 6 filament adapters; omits signals, growth, membership, moderation, references)
- Problem: No documented rule explains the selection. If the policy is "commerce checkout suite", `docs` fits but `filament-authz` (admin RBAC) is odd; if the policy is "everything stable", the omissions are arbitrary. Consumers cannot predict what a csuite install contains.
- Why It Matters: Unpredictable bundles get bypassed (everyone hand-picks packages), defeating the bundle's purpose.
- Recommended Fix: Adopt and document one policy in `docs/01-overview.md`: bundle = checkout-and-fulfillment suite (current set + authz from C1). Explicitly list non-bundled packages (signals/growth analytics, membership/moderation/references) with one-line "install separately when…" guidance. Do not silently bloat the bundle to everything.
- Breaking Change: NO
- Affected Packages: none (docs + one require line from C1)
- Required Dependent Changes: none
- Migration Required: NO

### C3 — `CONTEXT.md` claims domain ownership a metapackage cannot have
- Severity: Low
- Location: `packages/csuite/CONTEXT.md` (Guardrails: "Owns models, actions, services, events, calculations, and persistence rules")
- Problem: Copy-paste guardrail contradicts the file's own Snapshot ("No runtime code") and will misroute agents into editing csuite for behavior.
- Why It Matters: Stale routing docs cause exactly the wrong change in the wrong place.
- Recommended Fix: Replace guardrail with: "Owns nothing at runtime. Never add src/config/routes/migrations here; change the underlying package and update its docs in the same pass."
- Breaking Change: NO
- Affected Packages: none
- Required Dependent Changes: none
- Migration Required: NO

## Code Quality Findings (same finding format)

None — no code. (Docs-only nits such as the missing non-bundled list are covered by C2.)

## Laravel-Specific Findings

- `type: metapackage`, `php: ^8.4`, `self.version` pinning across the monorepo — correct metapackage hygiene; version coherence is automatic.
- No providers, no aliases, no publishes — correct (nothing to register).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction; N/A section for standalones csuite/membership/moderation/references)

- N/A — csuite is a metapackage with no adapter code. Adapter coherence of the bundled set (cart/cashier/chip/docs/jnt/inventory/vouchers filament packages) is covered in their own audits, not here.

## Database Findings

- None — no migrations by design. (If a future bundle needs seed/ordering guarantees, that belongs in install commands of the underlying packages, not here.)

## Model / Domain Findings

- None — no models by design.

## Security Findings

- Supply-chain surface only: the bundle pins `self.version` for all members (good — no floating majors). No action beyond keeping the require list accurate (C1/C2), since a wrong list is itself a supply-chain defect (missing security fixes from omitted packages go unnoticed).

## Performance Findings

- Install-time only: each added require marginally increases install surface. The C1 addition is one small package; no concern. Do not add suggested/dev dependencies to the metapackage.

## Testing Findings

- No bundle test exists. Required: one smoke test asserting the bundle resolves (every `require`d package's provider class exists + every bundled `filament-*` plugin class exists), failing on exactly the C1 class of error. Place under `tests/src/Csuite/BundleTest.php` (new dir) or the repo's existing install-test area if one exists; run with `./vendor/bin/pest --parallel tests/src/Csuite`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| authz | added to bundle (C1) | Ships with csuite installs | None (package unchanged) |
| all bundled packages | accurate bundle membership | No code impact | None |
| host apps installing csuite | corrected dependency set | `composer update` pulls authz; documented non-bundled list | None (additive) |

## Recommended Refactor Plan (ordered steps)

1. C1: add `aiarmada/authz` to require.
2. C2: document bundle policy + non-bundled list in `docs/01-overview.md`.
3. C3: fix `CONTEXT.md` guardrail.
4. Add bundle smoke test; run `./vendor/bin/pest --parallel tests/src/Csuite` (or equivalent path).

## Files Likely to Change

- `packages/csuite/composer.json`, `packages/csuite/CONTEXT.md`, `packages/csuite/docs/01-overview.md`, new bundle smoke test file.

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- The false guardrail lines in `packages/csuite/CONTEXT.md` ("Owns models, actions, services, events, calculations, and persistence rules." — replaced, not preserved; verified this package has no `src/` so the claim is provably false).
- Nothing else: no code exists to remove.

## Final Recommended Architecture

csuite stays a thin, truthful metapackage: a coherent require list (checkout-and-fulfillment + authz), docs stating the membership policy and the install-separately list, a routing context that forbids runtime code here, and one smoke test keeping the bundle honest.
