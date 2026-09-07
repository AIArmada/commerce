# Tax Audit

## Packages Reviewed
- `aiarmada/tax` (`packages/tax`): `src/` (Actions/Exemption, Console/Commands, Contracts, Data, Enums, Events, Exceptions, Facades, Models, Services, Settings, States), `config/tax.php`, `database/migrations/` (4 files), `composer.json`, `src/TaxServiceProvider.php`, `CONTEXT.md`/`README.md`/`docs/`
- `aiarmada/filament-tax` (`packages/filament-tax`): `src/` (Actions, Pages, Resources, Support, Widgets), `config/filament-tax.php`, `composer.json`, `CONTEXT.md`/`README.md`/`docs/`
- No `tests/` directory in either package (verified).

## Overall Assessment
- Quality: Good small engine. Clean `TaxCalculator` → `ZoneResolver` → `RateApplier` pipeline, basis-points rates, `MoneyNormalizer::toCents()` guard, immutable dates, Filament adapter correctly thin and nav-compliant.
- Health: Good, with one structural tenant-isolation gap (calculator bypasses owner scoping) and several consistency papercuts (custom `scopeForOwner` signature, hardcoded exemption fallbacks, unsaved-model fallback rate).
- Risks: In multi-tenant mode, `TaxCalculator::getRates()`/`checkExemption()` rely purely on ambient owner context instead of the checkout session's owner, so tax can silently compute against the wrong tenant's rates (or zero rates) when ambient context is absent or differs. No tests.
- Refactor size: Small–Medium. ~6–10 files, no migration required.

## Migration Impact
**Migration Required: NO**
No table/column/index/constraint changes. All fixes are code-only.

| Table | Change | Detail |
|---|---|---|
| `tax_zones`, `tax_classes`, `tax_rates`, `tax_exemptions` | none | uuid PKs kept; `nullableMorphs('owner')` kept; no FK constraints added (rule-compliant); no data migration |

## Package Responsibilities
- Owns: tax calculation engine (`TaxCalculator`, `StandardRateApplier`, zone resolvers), zone/rate/class/exemption models, exemption approval workflow (spatie states), `TaxSettings`/`TaxZoneSettings`, `TaxResultData`, events.
- Filament adapter owns: zone/class/rate/exemption resources, `ManageTaxSettings` page, exemption-certificate download action, coverage/expiry/stats widgets. Thin and correct.

## Architecture Findings
### A-1 Calculator bypasses owner scoping (tenant-isolation gap)
- Severity: High
- Location: `packages/tax/src/Services/TaxCalculator.php::getRates()` (`TaxRate::query()->where('zone_id', ...)`), `::checkExemption()` (`TaxExemption::query()->where('exemptable_id', ...)`), `packages/tax/src/Services/ZoneResolver/*.php`
- Problem: Direct model queries never enter an owner context (`OwnerContext::resolve()` / `forOwner()`), while `TaxZone`, `TaxRate`, `TaxClass`, `TaxExemption` all use `HasOwner` + `HasOwnerScopeConfig` (`tax.features.owner`). Re-check outcome (demoted Critical→High): the global `OwnerScope` still applies to these queries, so no `withoutGlobalScope` stripping was found in the calculator — the proven failure mode is reads blocked/emptied when ambient context is absent (silent zero-tax, a revenue bug), not a proven cross-tenant leak. A leak would additionally require a caller stripping scopes, which was not found. `checkout/Integrations/TaxAdapter.php` + `Steps/CalculateTaxStep.php` call the calculator inside the checkout session context (`CheckoutService::withSessionOwnerContext()`), so the happy path is ambient-covered; the calculator itself just does not propagate it explicitly.
- Why It Matters: Tax rates are tenant-owned configuration; cross-tenant reads are a data-isolation violation, and silent zero-tax results are a revenue bug.
- Recommended Fix: Resolve owner once in `TaxCalculator` (`OwnerContext::resolve()` or accept explicit `owner` in `$context`) and apply `forOwner($owner)` to the rate/exemption/zone queries; thread `owner` through `TaxZoneResolverInterface::resolve($zoneId, $context)`.
- Breaking Change: NO (context array is already arbitrary; add optional `owner` key)
- Affected Packages: `tax`, `checkout` (`Integrations/TaxAdapter.php`, `Steps/CalculateTaxStep.php`), `filament-tax`
- Required Dependent Changes: `TaxAdapter` passes `$session->owner` (or session owner context) into the calculator context.
- Migration Required: NO

### A-2 `scopeForOwner` signature diverges from the `HasOwner` contract
- Severity: Medium
- Location: `packages/tax/src/Models/TaxRate.php::scopeForOwner()` (also mirrored pattern in `TaxZone`, `TaxClass`, `TaxExemption`), vs `commerce-support` `HasOwner::scopeForOwner`
- Problem: Tax models define `scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true)` with a `true` default, then AND it with `config('tax.features.owner.include_global', false)`. The effective default is `false`, but the signature advertises `true` — every caller reading the signature misunderstands the behavior. `filament-tax` works around this by using `OwnerUiScope::apply(..., includeGlobal: false)` instead.
- Why It Matters: Inconsistent owner-scope spelling across the monorepo (`shipping` hand-rolls, `tax` overrides defaults, `chip` uses `forOwner()`); future callers will pass the wrong flag.
- Recommended Fix: Drop the custom override and use the trait's scope directly; express include-global purely via `tax.features.owner.include_global` config.
- Breaking Change: NO
- Affected Packages: `tax`, `filament-tax`
- Required Dependent Changes: None (call sites already pass explicit flags or use `OwnerUiScope`).
- Migration Required: NO

### A-3 Fallback "zero rate" fabricates unsaved models
- Severity: Medium
- Location: `packages/tax/src/Services/RateApplier/StandardRateApplier.php::apply()` (`TaxRate::zeroRate('standard', new TaxZone)`), `packages/tax/src/Models/TaxRate.php::zeroRate()`
- Problem: When no rates match, the applier invents an unsaved `TaxRate` (+ unsaved `TaxZone`) as `primary_rate`, which flows into `TaxResultData($rateId: $result['primary_rate']->id, ...)` — `id` is null — and into `TaxCalculated` event consumers (checkout `tax_data`, order snapshots, Filament displays).
- Why It Matters: Null `rate_id` in persisted `tax_data` JSON breaks downstream joins/displays and masks "no rate configured" as "zero rate applied".
- Recommended Fix: Make `primary_rate` nullable in the applier result + `TaxResultData`, or return a sentinel `rateId: null` with `rateName: 'no-rate'`; update `TaxAdapter`/checkout consumers to handle null.
- Breaking Change: YES (result array shape / `TaxResultData` nullability)
- Affected Packages: `tax`, `checkout`
- Required Dependent Changes: `checkout/Integrations/TaxAdapter.php`, `Steps/CalculateTaxStep.php`, any `TaxCalculated` listeners.
- Migration Required: NO

### A-4 Hardcoded exemption customer-type fallbacks
- Severity: Medium
- Location: `packages/tax/src/Services/TaxCalculator.php::checkExemption()` (`AIArmada\Customers\Models\Customer`, `App\Models\Customer`, `App\Models\User` candidate list)
- Problem: Guesses morph types when `customer_type` is absent, including a hard `App\Models\User` fallback. Any app using a different billable/customer model silently never matches exemptions (or matches the wrong morph alias).
- Why It Matters: Exemptions silently fail closed; `filament-tax` exemption admin shows approvals that never apply at checkout.
- Recommended Fix: Require explicit `exemptable_type` in context (resolve from the actual billable model via `Relation::getMorphClass()`); remove the hardcoded fallbacks.
- Breaking Change: YES (callers must pass `exemptable_type`)
- Affected Packages: `tax`, `checkout`, `filament-tax`
- Required Dependent Changes: `TaxAdapter` passes the session billable/customer morph class.
- Migration Required: NO

### A-5 No app-level cascade/orphan guard for `tax_rates.zone_id`
- Severity: Low
- Location: `packages/tax/src/Models/TaxZone.php` (no `deleting` cascade found), `database/migrations/2001_03_01_000003_create_tax_rates_table.php` (`foreignUuid('zone_id')`, no constraint — rule-compliant)
- Problem: Deleting a zone leaves orphan `tax_rates` rows that `getRates()` can never reach (it filters by `zone_id`) but that still show in Filament until manually cleaned.
- Why It Matters: Dead rates accumulate; admin UX degrades.
- Recommended Fix: Add `TaxZone::booted()` deleting-hook (`$zone->rates()->delete()`, same pattern as `shipping/Models/ShippingZone.php:107-108`) or block zone delete when rates exist.
- Breaking Change: YES (zone delete becomes cascading)
- Affected Packages: `tax`, `filament-tax`
- Required Dependent Changes: Filament delete confirmation copy.
- Migration Required: NO

## Code Quality Findings
### C-1 `round_at_subtotal` naming vs behavior
- Severity: Low
- Location: `packages/tax/src/Services/RateApplier/StandardRateApplier.php::apply()`, `config/tax.php::defaults.round_at_subtotal`
- Problem: Flag reads per-line (`(int) round($taxAmount)` inside both loops) but is named "subtotal" — actual subtotal-level rounding would sum unrounded line taxes then round once. Current behavior is per-rate rounding regardless of the flag's name.
- Why It Matters: Misleads anyone tuning rounding for compliance (MYR SST/GST rounding rules).
- Recommended Fix: Rename to `round_per_rate` or implement true subtotal rounding when false.
- Breaking Change: YES (config key renamed; breaking changes allowed — update readers in one pass)
- Affected Packages: `tax`
- Required Dependent Changes: None.
- Migration Required: NO

### C-2 Compound-tax base ignores `pricesIncludeTax` correctly but obscurely
- Severity: Low
- Location: `packages/tax/src/Services/RateApplier/StandardRateApplier.php` (`$compoundBase = $pricesIncludeTax ? $amountInCents : ($amountInCents + $totalTax)`)
- Problem: Correct math, but the branch is unexplained and untested; a future editor will "simplify" it into a bug.
- Why It Matters: Tax-compliance code without a single test or comment is a regression waiting to happen.
- Recommended Fix: Add a docblock with the formula + Pest cases (inclusive vs exclusive, compound on top of non-compound).
- Breaking Change: NO
- Affected Packages: `tax`
- Required Dependent Changes: None.
- Migration Required: NO

## Laravel-Specific Findings
- L-1 (Info, compliant): PHP `^8.4`; `HasUuids` on all four models; `getTable()` from `tax.database.tables.*`; `timestampsTz()`; `CarbonImmutable` in `TaxExemption`/`TaxExemptionState`; no `SoftDeletes`.
- L-2 (Low): `tax` migrations each define `down()` with `dropIfExists` while `shipping`/`checkout` use `commerce_schema_create_if_missing` without `down()` — pick one convention (keep `down()`; harmless).
- L-3 (Low): `tax.database.tables` hardcodes names with no `table_prefix` support, unlike `shipping`/`checkout`/`chip` configs. Add `table_prefix` handling in `getTable()` + migrations for consistency (code-only; no rename migration — new installs pick up prefix, existing installs keep names via config).

## Filament Adapter Findings
- Thin-adapter check: PASS. Resources are CRUD over domain models; `ManageTaxSettings` edits `TaxSettings`; widgets read via models; certificate download is the only action and it belongs in UI.
- Domain leak: None found.
- Duplication: None.
- Dependency direction: Correct (`filament-tax` → `tax` + `filament-authz` + `commerce-support`).
- Navigation: COMPLIANT. Nested `navigation.group` + `settings_group` in `config/filament-tax.php`; all four resources + `ManageTaxSettings` use `getNavigationGroup()` from config; no static `$navigationGroup`.
- Noted inconsistency (info): `filament-tax` scopes with `OwnerUiScope::apply()` while `filament-shipping` hand-rolls `forOwner()` — standardize on `OwnerUiScope` everywhere (see shipping A-3).

## Database Findings
- D-1 (Compliant): uuid PKs on all four tables; `nullableMorphs('owner')`; `foreignUuid('zone_id')` / `foreignUuid('tax_zone_id')->nullable()` / `uuidMorphs('exemptable')` with zero `constrained()`/`cascadeOnDelete()` (verified by repo-wide grep). `json_column_type` configurable. Indexes on `(zone_id, tax_class, is_active)`, `(exemptable_type, exemptable_id, status)`, `(status, expires_at)` — good.
- D-2 (Low): No unique constraint on `tax_zones.code` or `tax_classes.slug` (only plain indexes) — duplicate codes/slugs possible from concurrent admin creates. Add app-level uniqueness validation in Filament forms + model rules (no DB constraint change required by repo rules; enforce in application logic).
- D-3 (Info): `tax_rates.rate` is `unsignedInteger` basis points (600 = 6.00%) — correct minor-unit-style money handling; `getRateDecimal()`/`calculateTax()`/`extractTax()` rounding is sound.

## Model / Domain Findings
- M-1: `TaxExemption` lifecycle (`pending` → `under_review` → `approved`/`rejected`, `expired`/`revoked`, `verified_at`/`starts_at`/`expires_at`/`revoked_at`) follows the timestamp-convention rules (`status` + `*_at` columns, non-terminal toggles) — good. `ApproveExemptionAction`/`RejectExemptionAction` set `verified_at` with `CarbonImmutable` — good.
- M-2: `TaxZone::matchesAddress`-equivalent logic lives in resolvers (`AddressZoneResolver`, `CompositeZoneResolver`, `DefaultZoneResolver`, `ZoneIdResolver`) — correct seam; keep.
- M-3: Display-only `number_format()` in `TaxResultData`/`TaxRate::getFormattedRate()` formats a percentage string, not money — acceptable (rule targets currency formatting; no raw money `number_format` found).

## Security Findings
- S-1 (Medium): Exemption document uploads (`document_path`, `TAX_CERTIFICATES_DISK`, `tax-exemptions/` directory) — verify disk is private, paths are not user-controlled, and `DownloadTaxExemptionCertificateAction` authorizes via policy + owner scope before streaming (same download-route discipline as shipping S-1).
- S-2 (Low): `RecalculateTaxRatesCommand` / `SyncTaxZonesCommand` bulk-update rates — ensure they run inside explicit owner iteration (`OwnerContext::withOwner` per owner) rather than ambient context, per background-work rules.
- S-3 (Info): No webhook/secret surface in tax. No amount-tampering surface (rates are server-side; amounts come from callers as cents).

## Performance Findings
- P-1 (Low): `getRates()` orders by `is_compound ASC, priority DESC` on an index of `(zone_id, tax_class, is_active)` — the sort is not covered; fine at this cardinality, revisit only with thousands of rates per zone.
- P-2 (Info): Calculator dispatches `TaxCalculated`/`TaxZoneResolved`/`TaxExemptionApplied` per line — ensure checkout calls it per cart total, not per unit, or listeners must be cheap.

## Testing Findings
- T-1 (Medium): Zero tests. Demoted from High per severity rubric (testability gaps are Medium, not incorrect behavior). Priority Pest cases (all `--parallel`): inclusive vs exclusive tax, compound stacking, zero-rate fallback (post A-3), exemption active/expired/zone-scoped matching, zone priority/default resolution, cross-tenant rate isolation (reuse `OwnerScopingContractTests`), Filament resource scoping.
- T-2: No factories — add `TaxZone`/`TaxRate`/`TaxExemption` factories alongside tests.

## Cross-Package Dependency Impact
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `checkout` (`Integrations/TaxAdapter.php`, `Steps/CalculateTaxStep.php`) | `TaxCalculatorInterface`, `TaxResultData`, exemption context | A-1/A-3/A-4 change calculator inputs/outputs | Pass session owner + explicit `exemptable_type`; handle nullable `rateId` |
| `pricing` (`Settings/PricingSettings.php`) | tax settings interplay | Low | None unless settings keys renamed |
| `cashier` (`Gateways/*/ChipInvoice`, `StripeInvoice`, `Contracts/InvoiceContract`) | duplicated tax display on invoices | Low | Keep invoice tax display reading calculator, not hand-rolled math |
| `cashier-chip` (`Invoice/Invoice.php`) | same | Low | Same as above |

## Recommended Refactor Plan
1. Thread owner context through `TaxCalculator` + resolvers (A-1) with `TaxAdapter` passing session owner.
2. Drop custom `scopeForOwner` overrides (A-2); standardize on trait scope / `OwnerUiScope`.
3. Make zero-rate fallback explicit/nullable (A-3).
4. Require explicit `exemptable_type` (A-4).
5. Add zone-delete guard (A-5) + uniqueness validation (D-2) + `table_prefix` parity (L-3).
6. Write the Pest suite + factories (T-1).

## Files Likely to Change
- `packages/tax/src/Services/TaxCalculator.php`
- `packages/tax/src/Services/ZoneResolver/*.php`
- `packages/tax/src/Contracts/TaxZoneResolverInterface.php`
- `packages/tax/src/Services/RateApplier/StandardRateApplier.php`
- `packages/tax/src/Data/TaxResultData.php`
- `packages/tax/src/Models/TaxRate.php`, `TaxZone.php`, `TaxClass.php`, `TaxExemption.php`
- `packages/tax/config/tax.php`
- `packages/checkout/src/Integrations/TaxAdapter.php`, `Steps/CalculateTaxStep.php`
- `packages/filament-tax/src/Resources/*.php` (validation only)

## Files / Code That Should Be Removed
- Custom `scopeForOwner()` overrides in `packages/tax/src/Models/TaxRate.php`, `TaxZone.php`, `TaxClass.php`, `TaxExemption.php` (delete overrides, use `HasOwner` trait scope — verified divergent default `includeGlobal = true` in `TaxRate.php::scopeForOwner()`).
- Hardcoded customer-type fallback list in `packages/tax/src/Services/TaxCalculator.php::checkExemption()` (delete `App\Models\Customer` / `App\Models\User` guesses; require explicit type).
- `TaxRate::zeroRate()` unsaved-model factory if A-3 nullable path is taken (else keep with explicit naming).
- No migration files removed. No legacy shims preserved.

## Final Recommended Architecture
Keep the engine shape (`TaxCalculator` → resolvers → `StandardRateApplier`, spatie-state exemption workflow, settings-backed defaults) and the thin Filament adapter. Fix only the seams: owner-aware calculator inputs, trait-standard scoping, explicit zero-rate/exemptable-type contracts, and app-level guards. No new packages.
