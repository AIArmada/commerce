End-to-end review: filament-tax, shipping, growth, filament-growth. 26 findings + positives. All paths relative to /Users/Saiffil/Herd/commerce.

## SHIPPING (packages/shipping)

S1 — HIGH/security — packages/shipping/src/Policies/ShippingZonePolicy.php:32-77 — Zone policy lacks owner boundary (cross-tenant IDOR). view/view/update/delete/manageRates check only `shipping.zones.*` permission, while ShipmentPolicy (ShipmentPolicy.php:65-80) and ReturnAuthorizationPolicy (ReturnAuthorizationPolicy.php:64-79) both add an `isOwner()` check when `shipping.features.owner.enabled` with explicit "prevent cross-tenant IDOR" comments. Any user with zone permissions can read/update/delete another owner's zones. Evidence: `public function update(...){ return $this->hasPermission($user,'shipping.zones.update'); }` vs ShipmentPolicy's owner check. Recommendation: copy the `isOwner()` + include_global pattern from ShipmentPolicy into all ShippingZonePolicy record methods. Confidence: high.

S2 — MEDIUM/security — packages/shipping/src/Integrations/OrderFulfillmentHandler.php:224 — getTracking() tracking-number oracle. `Shipment::where('tracking_number',$trackingNumber)->first()` relies solely on the global scope; with owner mode off it returns any shipment's status/events, and tracking numbers are low-entropy `uniqid()` (ManualShippingDriver.php:96 `MAN-`.mb_strtoupper(uniqid()); ZoneBasedShippingDriver.php:118 same pattern), enumerable. Recommendation: scope lookup to the requesting order/customer or require an unguessable token; use Str::ulid/random for tracking refs. Confidence: med.

S3 — MEDIUM/bug — packages/shipping/src/Actions/ApproveReturnAuthorization.php:27-34; RejectReturnAuthorization.php:27-34 — RMA approve/reject bypass the Spatie state machine via direct `$rma->update(['status'=>'approved'/'rejected',...])`, skipping transition validation and transition events (compare UpdateShipmentStatus/ShipShipment which use transitionTo). Mitigated by the isPending() guard. Recommendation: use `$rma->status->transitionTo(RmaApproved::class)` (+ timestamps). Confidence: med.

S4 — MEDIUM/performance — packages/shipping/src/Services/TrackingAggregator.php:163-168 (same in RecordTrackingEvent.php:39-42) — per-event `exists()` query inside loop; syncBatch compounds it per shipment. Evidence: `foreach ($events as $eventData) { $exists = $shipment->events()->where(...)->where(...)->exists(); ...}`. Recommendation: one `whereIn` fetch of existing (code, occurred_at) pairs per shipment. Confidence: high.

S5 — LOW/bug — packages/shipping/src/Services/RateShoppingEngine.php:149-156 — clearCache() is a silent no-op on non-taggable cache stores (file/database): only flushes inside `instanceof TaggableStore`. Recommendation: track keys or document taggable-store requirement; TTL 300 bounds staleness. Confidence: high.

S6 — LOW/bug — packages/shipping/src/Models/ShippingZone.php:242 — postcode range compare is lexicographic (`$postcode >= $from && $postcode <= $to`), so e.g. '50000' matches '1000'-'9999'. Low impact for fixed-length MY postcodes. Recommendation: numeric compare when both sides are digits, else length-aware compare. Confidence: med.

S7 — LOW/security — packages/shipping/src/Integrations/OrderFulfillmentHandler.php:309-313,339-349 — forced `location_id` from $shipmentData resolved via unscoped `InventoryLocation::find()`, never checked against the order's owner. Recommendation: scope to owner's locations. Confidence: med (caller input trust unknown).

S8 — LOW/performance — packages/shipping/src/Actions/RecalculateShipmentWeight.php:17 — `items()->get()->sum(...)` hydrates all items; CreateShipment.php:74 uses SQL `sum(weight*quantity)`. Recommendation: match the SQL aggregate. Confidence: high.

S9 — LOW/bug — packages/shipping/src/Models/ShipmentOperation.php:52-75 — recordStart() check-then-insert has no unique index; concurrent callers can double-create Pending rows. Mitigated by Cache locks in Ship/CancelShipment. Recommendation: unique index on (shipment_id, operation_type, status) or insert-catch. Confidence: med.

S10 — LOW/performance — packages/shipping/src/Services/BatchRateLimiter.php:191-224 — rate key has no owner/tenant segment (cross-tenant contention) and `sleep($retryAfter)` blocks the request up to 30s. Recommendation: include owner scope in key; move bulk sync off-request. Confidence: med.

## GROWTH (packages/growth)

G1 — MEDIUM/bug — packages/growth/src/Console/Commands/RecomputeExperimentAssignmentsCommand.php:29; ArchiveExperimentsCommand.php:31 — wrong owner config key: `new OwnerBatchRunner(X::class, ['enabled'=>'commerce-support.owner.enabled'])`, but growth's flag is `growth.features.owner.enabled` (config/growth.php:78-82). OwnerBatchRunner::isOwnerDisabled() (commerce-support/src/Support/OwnerBatchRunner.php:81-85) therefore mis-detects: growth-owner-on + commerce-key-off runs the callback with no owner iteration (global OwnerScope then throws, command crashes); inverse runs N redundant passes. Recommendation: use `growth.features.owner.enabled`. Confidence: high (mismatch verified; crash path med).

G2 — MEDIUM/performance — packages/growth/src/Actions/AggregateExperimentMetrics.php:60-66 — handle() loads ALL assignments and ALL matching signal events unbounded (`->get()`), per experiment; handleMany's UNION is likewise unbounded. Recommendation: chunk/stream or cap with explicit windowing. Confidence: high.

G3 — MEDIUM/performance — RecomputeExperimentAssignmentsCommand.php:34-41 + ResolveExperimentAssignment.php:248-266 — repair loop calls variantForSubject() → pickVariant() → full variant query PER assignment (100k assignments = 100k queries). Recommendation: cache active variants per experiment_id for the run. Confidence: high.

G4 — LOW/bug — packages/growth/src/Support/Context/ExperimentResolver.php:23-43 vs :166-173 — resolve() accepts ResolveStrategy but never applies the Readable filter (only resolveBySlug does), so AggregateExperimentMetrics and BuildExperimentSignalProperties pass Readable expecting active-only and silently get any status. Recommendation: apply applyReadableFilter() in resolve() or remove the dead parameter. Confidence: high.

G5 — LOW/bug — packages/growth/src/Support/ExperimentAssignmentResolver.php:143-149 vs Actions/ResolveExperimentAssignment.php:98-113 — attribution builds raw `'anonymous:'.$id` but storage hashes IDs over ~245 chars (`anonymous:sha256:...`), so long anonymous IDs never match → unattributed events. Recommendation: share one canonicalizer. Confidence: med.

G6 — LOW/robustness — packages/growth/src/Http/Middleware/ResolveExperiment.php:37-44 — catches InvalidArgumentException but not AuthorizationException from resolveBySlug(), so a cross-owner slug yields a storefront 403/500 instead of skipping. Recommendation: also skip on AuthorizationException (or fail closed deliberately with logging). Confidence: med.

G7 — LOW/bug — packages/growth/src/Console/Commands/ArchiveExperimentsCommand.php:25,36-41 — unbounded `->get()` and unvalidated `--older-than` (negative → future threshold → archives all concluded). Recommendation: chunk + `max(0,...)`/validate. Confidence: high mechanics, low impact (console operator).

G8 — LOW/bug — packages/growth/src/Actions/AggregateExperimentMetrics.php:238-254 — handleMany uses Postgres-only `CAST(NULL AS uuid/timestamptz)`; breaks MySQL/SQLite despite `commerce_json_column_type` multi-DB support. Recommendation: driver-aware casts. Confidence: med.

G9 — LOW/correctness — packages/growth/src/Support/Http/DefaultRequestExperimentSubjectResolver.php:69-73 — external_id fallback lookup lacks the auth_user_type filter the primary query has → colliding identifiers across user types merge assignments. Recommendation: add morph-class filter. Confidence: low-med.

G10 — LOW/performance — packages/growth/src/Models/Assignment.php:131-145 — every save (including last_seen_at touches) re-resolves experiment+variant+identity+session (up to 4 queries) on the hot assignment path. Recommendation: skip consistency check when only timestamps/metadata changed. Confidence: med.

## FILAMENT-TAX (packages/filament-tax)

T1 — HIGH/security — TaxZonesTable.php:68-71; TaxRatesTable.php:107-110; TaxClassesTable.php:57-59; TaxExemptionsTable.php:135-183 (DeleteAction.php:181 bare); RatesTable.php:50-56 — row-level View/Edit/Delete/Create actions have NO ->authorize(), resources define no can*/policy overrides, and no policies exist anywhere in tax or filament-tax (verified by search); only bulk actions check `tax.*` permissions. Filament default-allows without policies, so any panel user can create/edit/delete tax config and reach `/{record}/edit` URLs directly. Recommendation: add ->authorize('tax.zones.update' etc.) to every row/header action + canCreate/canEdit/canDelete on resources (or register policies). Confidence: med-high (all code facts verified; Filament default-allow not re-verified in vendor since vendor isn't searchable).

T2 — MEDIUM/bug — packages/filament-tax/src/Pages/ManageTaxSettings.php:143-170 — save() persists `$this->data` directly, never `$this->form->getState()`, so numeric bounds (defaultTaxRate 0-100), required, and Select-option constraints are unenforced server-side (unbounded rate, arbitrary taxIdLabel). Recommendation: `$state = $this->form->getState()` + validation rules. Confidence: high.

T3 — LOW/security — TaxExemptionForm.php:52-123 — exemptable_id search + option-label queries use raw `$type::query()`, relying on the customers package's global scope; inconsistent with the taxZone relationship in the same form which uses OwnerUiScope::apply (line 130). Cross-owner customer name/email disclosure if customers owner mode is off. Create/edit revalidation (CreateTaxExemption.php:27-43) covers writes only. Recommendation: wrap in OwnerUiScope::apply like taxZone. Confidence: med.

T4 — LOW/bug — TaxExemptionForm.php:141-144 — certificate_number `->unique(ignoreRecord:true)` is global, not owner-scoped (unlike zone code at TaxZoneForm.php:38-50) → cross-tenant collision blocks creation. Confidence: high mechanics, low impact.

T5 — LOW/security — TaxExemptionsTable.php:156-182 — row approve/renew/delete act on $record without the OwnerWriteGuard revalidation their bulk siblings perform. Relies on the scoped table query. Recommendation: re-verify per record. Confidence: med.

T6 — LOW/performance — TaxExemptionsTable.php:34,48; ExpiringExemptionsWidget.php:27 — `exemptable.*`/`taxZone.name` columns with no eager loading → per-row queries (morph). Recommendation: eager-load where possible / accept for morph. Confidence: med.

## FILAMENT-GROWTH (packages/filament-growth)

FG1 — MEDIUM/security — packages/filament-growth/src/Pages/ManageGrowthSettings.php:51-58,89-98 — global experiment-middleware kill switch gated only by `viewAny Experiment`; any experiment viewer can disable all request-time assignment. No dedicated permission (contrast tax.settings.manage). Recommendation: add `growth.settings.manage` permission + HasPageAuthz-style gate. Confidence: high.

FG2 — MEDIUM/performance — packages/filament-growth/src/Widgets/ExperimentWinnersWidget.php:42-81 — up to 5 sequential full AggregateExperimentMetrics::handle() calls (each loading all assignments+events per G2) instead of the existing handleMany() batch path. Recommendation: use handleMany(). Confidence: high.

FG3 — LOW/performance — ExperimentResultsPage.php:261-268 — experimentOptions() loads ALL experiments into a preloaded Livewire select, unbounded. Recommendation: paginate/lazy-search the select. Confidence: high.

FG4 — LOW/bug — ExperimentResultsPage.php:135,149,263,286; ExperimentWinnersWidget.php:44; VariantsTable.php:39 — raw `Experiment::query()` relying solely on the global scope, bypassing this package's own TrackedProperty-aware resolution (VariantForm::scopeAccessibleExperiments). Safe today only because GrowthServiceProvider.php:73-87 boots-hard on growth/signals owner-mode mismatch. Recommendation: use OwnerUiScope::apply / scopeAccessibleExperiments for consistency. Confidence: med.

FG5 — LOW/bug — Support/ExperimentHelpers.php:36-39 — canDeleteAnyExperiment() unconditionally true; bulk delete always visible (per-record canMutateRecord fail-closes, so only UX noise). Recommendation: mirror ExperimentPolicy::deleteAny. Confidence: high mechanics, low impact.

## Positives (brief)
- Shipping: LabelController defense-in-depth (signed URL + token + auth user + owner match); Shipment/RMA policies pair permissions with owner boundary + state guards; Ship/Cancel idempotent (Cache locks + ShipmentOperation ledger + retry with backoff/jitter); ShippingZoneResolver owner-keyed per-request cache with Octane-scoped bindings; CreateShipment enforces owner context; cart rate selection re-matches live engine quotes (no client price trust); migrations indexed, uuid PKs, no FK/SoftDeletes.
- Growth: strong owner hygiene (ExperimentResolver, Assignment/Variant parent-consistency guards, ScopeSignalQueryToOwner, OwnerWriteGuard); sticky assignments with lockForUpdate + unique-violation retry; tracked_property_id immutability; owner-keyed request caches; ExperimentContextManager singleton holds no state (Octane-safe).
- Filament-tax: bulk actions re-verify via OwnerWriteGuard; exemptable revalidation on create/edit; certificate download has traversal guard + owner visibility check + private disk + restricted upload types; owner-scoped zone-code uniqueness; all blades escaped.
- Filament-growth: Experiment/Variant policies + canEdit/canDelete overrides; variant settings allowlist normalization wired into create/edit; GrowthStatsAggregator batched UNION + window functions with safe fallbacks; blades escaped; no `{!!}` anywhere.
- Cross-cutting: money in int minor units; no DB FK/cascades/SoftDeletes; uuid PKs; no SSRF/Http-client, deserialization, or injection sinks found in scope; repo-level Pest coverage exists (tests/src/Shipping, Growth, Tax).

## Not verified / unresolved
- Filament v5 default-allow without policies (vendor/ not searchable) — T1 severity hinges on it; code-side facts (no authorize, no policies, no can* overrides) are verified.
- Whether any storefront/guest route reaches OrderFulfillmentHandler::getTracking (orders package out of scope) — affects S2 exploitability.
- handleMany CAST portability (G8) against the repo's actually-supported DB list.
