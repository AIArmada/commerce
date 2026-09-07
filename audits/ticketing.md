# Ticketing Audit

## Packages Reviewed (bullets)
- `packages/ticketing` (`aiarmada/ticketing`) — ticket types (polymorphic ticketable), components/bundles, products, seating options, passes, holders, transfers, issuance/delivery/transfer services
- `packages/filament-ticketing` (`aiarmada/filament-ticketing`) — Filament v5 admin: ticket-type/pass/holder/transfer resources, `TicketableTypeRegistry`

## Overall Assessment (quality, health, risks, refactor size)
- Quality: high. Smallest well-formed domain in the events-family (7 models, 8 migrations incl. owner-columns backfill, 8 actions, 3 services + null, spatie states + data). All 7 models `HasOwner`-scoped, `OwnerContextJob` on the queued job, `TicketingOwnerGuard` for polymorphic writes, correct `OwnerContext::withOwner(null)` batch command.
- Health: good, with 2 notable defects: (1) `DefaultPassIssuer::issuePassesFor()` saves passes one-by-one in a loop with per-pass `PassIssued` events and a `withoutOwnerScope()` uniqueness probe per pass; (2) `TicketableTypeRegistry` (the polymorphic allowlist that gates `TicketTypeResource::getEloquentQuery()`) lives in the FILAMENT package while core `TicketingOwnerGuard`/`TicketingIntegration` need the same knowledge. Re-check 2026-09-07: `config('ticketing.defaults.pass_no_prefix')` matches `config/ticketing.php:21-23` (`defaults.pass_no_prefix`) — no drift, verified; the earlier drift note is withdrawn.
- Risks: events package forks 4 ticketing classes (see events audit A1) — canonical ticketing must absorb the one useful extra (`fromTicketType` quota logic) and stay the single source of truth. Pass-code uniqueness loop is a contention point under flash sales.
- Refactor size: S (1–2 days). Batch issuance, registry move, config-key fix, Filament empty-state fix.

## Migration Impact
**Migration Required: NO**
- Tables/columns: no changes. 8 migrations (`ticket_types`, `ticket_type_components`, `ticket_type_products`, `passes`, `pass_holders`, `pass_transfers`, `ticket_type_seating_options` + `2026_09_05 owner columns` backfill) all use `uuid('id')->primary()`.
- Indexes/constraints: no FK constraints/cascades (verified) — compliant. Recommend verifying unique index on `passes.pass_no` + `(ticket_type_id, status)` composite, but no DDL in this audit.
- Data migration: none. Issuance batching, registry relocation, and config-key fixes are code-only.

## Package Responsibilities
- Core owns: ticket-type lifecycle (`EnsureTicketTypeAction`, bundle/component expansion), issuance (`IssuePassesAction`, `Services/DefaultPassIssuer.php`, `Support/PassIssuanceContext.php`), transfers (`TransferPassToHolderAction`, `BulkTransferPassesAction`, `RevokePassAction`, `Services/DefaultPassTransferService.php`, `PassTransferPolicy`), delivery (`PassDeliveryServiceInterface`, `DefaultPassDeliveryService`, `NullPassDeliveryService`, notifications `TicketNotification`, `PassTransferredTo*`), cart (`AddTicketTypeToCartAction`), order-paid issuance (`Listeners/IssuePassesOnOrderPaid.php`), seat release on pass terminal states (4 listeners), pricing-mode resolution (`Support/PricingModeResolver.php`, `Enums/PricingMode.php`, `BundleInclusionMode.php`), owner guard (`Support/TicketingOwnerGuard.php`), integration (`Support/Integration/TicketingIntegration.php`).
- Filament adapter owns: 4 resources + pages, `TicketableTypeRegistry`, plugin/provider.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)
1. Severity: High. Location: `packages/filament-ticketing/src/Support/TicketableTypeRegistry.php` (registry + `TICKETABLE_INTERFACE` const + `all()` reading `config('filament-ticketing.ticketable_types')` / `allowed_ticketable_types`) vs core `packages/ticketing/src/Support/TicketingOwnerGuard.php`, `Support/Integration/TicketingIntegration.php`, `Contracts/TicketableInterface.php`. Problem: the polymorphic allowlist lives in the Filament adapter, but core needs it (owner guard validates `ticketable_type`; integration resolves ticketables). Any non-Filament consumer (API, console, queue) cannot resolve allowed types without booting Filament config. Why It Matters: inverted dependency — domain knowledge in the adapter. Recommended Fix: move `TicketableTypeRegistry` to core (`AIArmada\Ticketing\Support\TicketableTypeRegistry`, config `ticketing.ticketable_types`), update all imports in the same pass (`TicketTypeResource.php:10,49,69`, config keys). No re-export, no alias, no merge-source — clean move only. Breaking Change: YES (namespace + config key move). Affected Packages: `filament-ticketing`, `events` (ticketable hosts: Event/Occurrence/Session), `orders`/`cart` (line resolution). Required Dependent Changes: update imports + publish new config key. Migration Required: NO.
2. Severity: Medium. Location: `packages/ticketing/src/Services/DefaultPassIssuer.php:17-50` (per-pass `new Pass` + `save()` + `event(new PassIssued)` in `for` loop) + `:52-61 generatePassNo()` (per-pass `Pass::query()->withoutOwnerScope()->where('pass_no', ...)->exists()` probe). Problem: N inserts + N events + N uniqueness probes for quantity N; no transaction wrapping the batch (partial issuance on failure). Why It Matters: flash-sale issuance of hundreds of passes is slow and can leave partial batches. Recommended Fix: wrap in `DB::transaction()`, pre-generate the batch of unique `pass_no`s with a single `whereIn('pass_no', $candidates)` collision check (retry only collisions), `Pass::insert()` the batch (or keep model creates inside the transaction if model events are required), then dispatch `PassIssued` per pass after commit (or one `PassesIssued` batch event — prefer per-pass after-commit to keep existing listeners working). Breaking Change: NO (same events, same order). Affected Packages: `events` (`IssueEventRegistrationPassesAction`, `IssueEventPassesStep`), `orders` (`IssuePassesOnOrderPaid`). Required Dependent Changes: none (behavior-preserving). Migration Required: NO.
3. Severity: Low. Location: `packages/ticketing/src/Support/TicketingOwnerGuard.php:134-144 isOwnerScopedModel()` (sniffs `HasOwner` via `class_uses_recursive` + `EventOwnerScope::supports()` via `class_exists` + `is_callable`). Problem: stringly-typed cross-package sniffing of events' custom scope (fragile; breaks silently when events migrates to `HasOwner` per events audit A2). Why It Matters: guard silently downgrades to unscoped for renamed scope classes. Recommended Fix: replace with interface check — introduce `OwnerScoped` marker usage already implied by `HasOwner`, and check `$relatedClass` uses `HasOwner` OR is in the core `TicketableTypeRegistry`; drop the `EventOwnerScope` string sniff in the same pass as the events A2 migration (coordinate the two). Breaking Change: NO (behavior-preserving if coordinated). Affected Packages: `events`. Required Dependent Changes: events A2. Migration Required: NO.

## Code Quality Findings (same finding format)
1. Severity: Low. Location: `packages/ticketing/src/Data/TicketTypeData.php` (write/input DTO: name/code/price/...) vs events' read-model `TicketTypeData::fromTicketType()` (id/name/quota/sales windows). Problem: canonical DTO lacks the read-model constructor events built for itself. Why It Matters: blocks deletion of the events fork (events audit A1 depends on this). Recommended Fix: add `TicketTypeData::fromTicketType(TicketType $t): self` named constructor to the canonical DTO (quota via `inventoryLevels()->exists() ? getTotalOnHand() : null` when `inventory` installed, else null via `class_exists` guard), plus `PassData::fromPass()`. Breaking Change: NO (additive). Affected Packages: `events`. Required Dependent Changes: events deletes its forks and calls these. Migration Required: NO.
2. Severity: Low. Location: `packages/ticketing/src/States/` (8 files: `PassState` + 7 states) — clean single state machine, no enum split (unlike affiliates). No action. Recorded as PASS, not a finding.

## Laravel-Specific Findings
- PHP 8.4, strict types, UUID PKs, `HasUuids`, `getTable()` from config, `json_column_type` (`ticketing.features`? — CONTEXT lists `ticketing.php: database/table_prefix/json_column_type/tables/...`; verified `json_column_type` key exists via config grep): PASS.
- Owner scoping: all 7 models `HasOwner` + `HasOwnerScopeConfig` with `ticketing.features.owner` key. Non-standard key nesting (`features.owner` vs conventional `owner`) — works (key is just a string) but inconsistent with `seating.owner`/`engagement.owner`/`feedback.owner`; normalize to `ticketing.owner` in the same pass as the registry move (config-only; update the 7 `$ownerScopeConfigKey` values + config file). Breaking Change: YES (config key rename — update all internal consumers; no external override expected beyond config publish). Affected Packages: none external. Required Dependent Changes: update the 7 model key lines + config. Migration Required: NO.
- Queues: `BulkSendTransferNotificationsJob` implements `OwnerScopedJob` + `OwnerContextJob` — exemplary. `ExpireTransfersCommand` uses `OwnerContext::withOwner(null)` + `OwnerBatchRunner` — exemplary.
- No FK constraints/cascades, no soft deletes: PASS. `CarbonImmutable`: PASS (spot-checked).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)
- Thin-adapter: PASS with one exception (the registry location, finding A1). Resources are standard forms/tables/infolists; no business logic.
- Domain leak (Low): `TicketTypeResource::getEloquentQuery():47-61` — `app(TicketableTypeRegistry::class)->all()` then `whereRaw('1 = 0')` when empty. The empty-registry empty-state hides ALL ticket types instead of surfacing misconfiguration. Recommended Fix: when registry is empty, return the unfiltered owner-scoped query and flash a configuration warning (or abort with a clear exception in local/debug) instead of `whereRaw('1 = 0')`. Breaking Change: NO. Affected Packages: none (admin UX only). Required Dependent Changes: none. Migration Required: NO.
- Hardcoded select options: `access_type`/`status` option lists are hardcoded in `TicketTypeResource::form()/table()` (`general_admission/reserved_seating/vip/complimentary`, `draft/active/paused/sold_out/ended/cancelled`) while `visibility` correctly uses `TicketTypeVisibility::options()`. Recommended Fix: add `TicketAccessType`/`TicketTypeStatus` enums (or reuse existing) and use `::options()` consistently. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
- Dependency direction: CORRECT (requires `aiarmada/ticketing`). PASS. Navigation: PASS (`getNavigationGroup` from nested config + `getNavigationSort` from `resources.navigation_sort.ticket_type`).
- Owner scoping: MIXED. `PassResource` uses `OwnerUiScope::apply(..., includeGlobal: false)` (good); `PassTransferResource`/`PassHolderResource` have custom `getEloquentQuery()` (verify they also apply `OwnerUiScope` — sampled only signatures); `TicketTypeResource` scopes via `whereHasMorph(ticketable)` + `OwnerUiScope::apply` on the morph query but NOT on the ticket-type query itself — confirm ticket types inherit owner via ticketable (they do per `TicketingOwnerGuard`, but the resource should still scope the base query; verify during refactor).

## Database Findings
- 8 migrations incl. explicit `2026_09_05 owner columns` backfill — evidence the owner rollout was done properly as a follow-up migration. Good practice; keep as reference for other packages.
- `pass_no` uniqueness: application-level `do/while exists()` probe (finding A2) plus a verified UNIQUE DB index on `pass_no` (re-check 2026-09-07: `ticket_types`-adjacent `passes` migration has `$table->string('pass_no')->unique()` with `qr_code`/`barcode` also unique) — backstop exists, no migration.
- `qr_code` (`Str::uuid`) + `barcode` (`Str::random(16)`) generated in PHP — fine; confirm uniqueness indexes per verification need.

## Model / Domain Findings
- Polymorphic `ticketable` (`TicketableInterface`: `transferWindowEndsAt()`) is the right seam for event/occurrence/session/product hosts. `TicketTypeSeatingOption` links ticket types to seating — correct seam direction (ticketing depends on seating models, not vice versa).
- Bundle model (`TicketTypeComponent` + `BundleInclusionMode` + `AutoAddRequired/Expand` actions) is sound; the events-fork deletion makes this canonical.
- Pass lifecycle states (`Pending/Issued/Activated/Used/Expired/Revoked/Cancelled/Voided`) single-machine via `PassState` — exemplary vs affiliates' split. `PassTransfer` expiry command exists. PASS.

## Security Findings
1. Severity: Low. Location: `Services/DefaultPassIssuer.php:58` uniqueness probe uses `withoutOwnerScope()` — necessary (pass_no global uniqueness) and read-only. PASS with note: keep the global window to the probe only (it is). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: transfer flow (`TransferPassToHolderAction`, `BulkTransferPassesAction`, `PassTransferPolicy`, `transfer_expires_at` from `transferWindowEndsAt()`). Confirm policy checks holder identity + window enforcement server-side (not just UI). No defect found at depth; test requirement. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Performance Findings
1. Severity: Medium. Location: `DefaultPassIssuer` loop (finding A2) — batch it. Also `generatePassNo()` `Str::random(8)` (62^8 space) with per-pass probe: fine at low volume, collision-retry under bulk needs the batched `whereIn` approach from A2. Breaking Change: NO. Affected Packages: `events` (`IssueEventRegistrationPassesAction`, `IssueEventPassesStep`), `orders` (`IssuePassesOnOrderPaid`) — same as A2, behavior-preserving. Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: 4 seat-release listeners (`ReleaseSeatsOnPass{Cancelled,Expired,Revoked,Voided}`) each fire per pass event — after batching issuance, ensure bulk paths (`BulkTransferPassesAction`, revoke-all) release seats in bulk, not per-pass. Breaking Change: NO. Affected Packages: `seating` (seat release). Required Dependent Changes: none. Migration Required: NO.

## Testing Findings
- Severity: High. Zero tests. Minimum Pest suite: issuance batch (quantities, pass_no uniqueness incl. collision retry), transfer + expiry (`ExpireTransfersCommand`), revoke/release-seat chain, bundle auto-add/expansion matrix, pricing-mode resolver, `TicketingOwnerGuard` accept/deny matrix, cross-tenant isolation for all 7 models, registry allowlist behavior (unknown ticketable rejected). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `events` | DTOs/actions (forked — reverse dep) | High — 4 duplicate classes (duplicated implementations per rubric; agrees with events audit A1) | Delete forks; consume canonical DTOs + add quota constructor here |
| `seating` | seat models for `TicketTypeSeatingOption`, release listeners | Medium — ticketing calls seating release | No change; keep listener direction (pass events → seat release) |
| `orders` | `IssuePassesOnOrderPaid` | High — paid→pass chain | No API change; gains batched issuance |
| `cart` / `checkout` | `AddTicketTypeToCartAction` | Low | No change |
| `inventory` (optional) | `inventoryLevels()->exists()` / `getTotalOnHand()` | Low — quota display | Guard with `class_exists` in new `fromTicketType()` |
| `filament-ticketing` | registry, all models | High — registry move | Update imports + config keys |

## Recommended Refactor Plan (ordered steps)
1. Move `TicketableTypeRegistry` to core (`ticketing.ticketable_types`); update Filament imports + config.
2. Normalize owner key `ticketing.features.owner` → `ticketing.owner` (7 models + config).
3. Batch `DefaultPassIssuer` (transaction + `whereIn` collision check + after-commit events).
4. Pin `pass_no_prefix` config-key resolution with a test (re-check 2026-09-07: no drift — code and config both use `ticketing.defaults.pass_no_prefix`; test only, no fix).
5. Add `fromTicketType()`/`fromPass()` constructors; unblock events fork deletion.
6. Fix Filament empty-registry `whereRaw('1 = 0')` + hardcoded option lists (enums).
7. Verify `pass_no` unique index + base-query scoping on all 4 Filament resources.
8. Write Pest suite.

## Files Likely to Change
- `packages/ticketing/src/Support/TicketableTypeRegistry.php` (new location), `config/ticketing.php`
- `packages/ticketing/src/Services/DefaultPassIssuer.php`, `src/Data/TicketTypeData.php`, `src/Data/PassData.php`
- `packages/ticketing/src/Models/*.php` (7 owner-key lines)
- `packages/filament-ticketing/src/Support/TicketableTypeRegistry.php` (delete after move), `src/Resources/TicketTypeResource.php`, `config/filament-ticketing.php`
- `packages/ticketing/src/Support/TicketingOwnerGuard.php` (coordinate with events A2)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)
- `packages/filament-ticketing/src/Support/TicketableTypeRegistry.php` — moved to `ticketing/src/Support/TicketableTypeRegistry.php` (verified consumers: `TicketTypeResource.php:10,49,69` + config; update all in same pass)
- `whereRaw('1 = 0')` empty-state in `TicketTypeResource::getEloquentQuery():52` — replaced with scoped query + config warning
- Hardcoded `access_type`/`status` option arrays in `TicketTypeResource` (replaced by enum `::options()`)

## Final Recommended Architecture
- `ticketing` = canonical polymorphic ticket/pass authority: core-owned `TicketableTypeRegistry`, batched transactional issuance, single state machine, `HasOwner` throughout with conventional `ticketing.owner` key. Events consumes (never forks); seating releases on pass events; Filament is a pure admin.
