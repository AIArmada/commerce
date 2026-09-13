End-to-end review: packages/filament-addressing (Filament v5 adapter over core `addressing`; no migrations/routes/jobs/commands/widgets in package — correct per adapter-only guardrail).

FINDINGS

1) [medium | bug | src/Resources/AddressResource/Pages/CreateAddress.php:25 | CreateAddress ignores configured model override | high]
Description: `handleRecordCreation` hardcodes `$address = new Address;` while `AddressResource::getModel()` honors `filament-addressing.resources.addresses.model`. A host overriding the model gets the base class on create (wrong table/behavior) but the custom class everywhere else.
Evidence: `$address = new Address; $address->fill($data); $address->save();` vs `AddressResource::getModel()` returning `config('...addresses.model', Address::class)`.
Recommendation: Resolve via `$model = AddressResource::getModel(); $address = new $model;` (same pattern `AddressAreaImporter::resolveRecord()` uses).

2) [medium | bug+security | src/Exports/AddressExporter.php:14 | AddressExporter hardcodes model and has no owner scoping | high (hardcoded model) / med (scope leak)]
Description: Unlike `AddressAreaExporter`/`AddressCountryExporter` (which override `getModel()` from config), `AddressExporter` hardcodes `protected static ?string $model = Address::class`. It also defines no query scoping, while `Address` is owner-scoped (`HasOwner`) and the resource applies `OwnerUiScope::apply(..., includeGlobal: false)` — exports may bypass the resource's owner scope depending on Filament's exporter query path. Mitigated by default: `features.address_export` is false.
Evidence: `protected static ?string $model = Address::class;` with no `getModel()`/`modifyQuery` override.
Recommendation: Override `getModel()` from config like the sibling exporters, and scope the export query with `OwnerUiScope` (e.g. `modifyQueryUsing`/`modifyQuery` hook) + a test asserting cross-owner rows are excluded.

3) [medium | bug | src/Resources/PostalCodeResource/Pages/ListPostalCodes.php:20-27 | Postcode import/export actions have no importer/exporter and config key is missing | med]
Description: `ImportAction::make()->label('Import Postcodes')` and `ExportAction::make()->label('Export Postcodes')` specify no `->importer()`/`->exporter()` class, so enabling them throws at runtime. `features.postal_code_import` does not even exist in default config (only `postal_code_export`, false). Compare `ListAddressAreas`, which wires both classes.
Evidence: lines 20–27 vs `ListAddressAreas` `->importer(AddressAreaImporter::class)` / `->exporter(AddressAreaExporter::class)`.
Recommendation: Add the missing importer/exporter classes (or remove the actions), and add both keys to default config.

4) [medium | performance | src/Tables/AddressAreaTable.php:100-143 | Uncached distinct scans over the areas table on every table render | high (code) / med (impact)]
Description: Each render runs 4 uncached `distinct` queries over all areas (`type`, `level`, `source` options + `country_code` filter pluck at lines 74–82) plus a distinct scan of `address_area_roles`. Imported area datasets can be large, making every list view pay full-table scans.
Evidence: `getTypeOptions()/getLevelOptions()/getSourceOptions()` each do `$areaClass::query()->distinct()->orderBy(...)->pluck(...)`.
Recommendation: Cache option lists with a short TTL (invalidate on import/save), or convert to `relationship()`/static-enum filters.

5) [medium | performance | src/Tables/AddressAreaTable.php:26,43,54; src/Tables/PostalCodeTable.php:20; src/Tables/AddressCountryTable.php:29 | Relationship columns with no explicit eager loading (N+1 risk) | med]
Description: List columns traverse relations per row (`names.name`, `roles.role`, `parent.name`, `areas.name`, `currencies.code`, `country/state.name`) but no table applies `->with([...])` via `modifyQueryUsing`. Each page of N rows can issue O(N) extra queries unless Filament auto-eager-loads the path.
Evidence: `TextColumn::make('names.name')->listWithLineBreaks()`, `->make('roles.role')`, `->make('parent.name')`, `->make('areas.name')` with no `with()` anywhere in `src/Tables`.
Recommendation: Add explicit eager loads (`names`, `roles`, `parent`, `areas`, `currencies`, `country`, `state`) per table and verify query counts.

6) [medium | performance | src/Schemas/AddressFormSchema.php:67-77 (+34-42) | State/country option loading is unbounded and uncached | high]
Description: The `state_id` options closure only filters `when($get(country_code))` — with no country selected it plucks the entire global states table into the dropdown on initial render. Country options (here and `AddressAreaFormSchema.php:34-40`) hydrate full `AddressCountry` models via `->get()` + `mapWithKeys` on every form render instead of `pluck`, with no caching (~250 rows × per render).
Evidence: `->options(fn... => ModelResolver::stateClass()::query()->when($get(...), ...)->orderBy('name')->pluck('name','id')->toArray())`.
Recommendation: Return `[]` when country is blank (field is hidden then anyway via `countryHasStates`), switch country loads to `pluck`, and consider caching the country list.

7) [low | bug | src/Schemas/AddressFormSchema.php:140-145 | LIKE wildcards in area search are not escaped | high]
Description: `"%{$search}%"` interpolates raw user input into `like`/`ilike`, so `%`/`_` act as wildcards (e.g. `%%%` matches everything up to the limit) — wrong results and wasted scans.
Evidence: `->where('name', $operator, "%{$search}%")->orWhere('slug', ...)->orWhere('code', ...)`.
Recommendation: Escape `%`, `_`, `\` in `$search` before interpolating.

8) [low | bug | src/Resources/PostalCodeResource/Pages/CreatePostalCode.php:10, EditPostalCode.php:10 | Postcode create/edit pages lack the read-only canAccess guard | high]
Description: Every other editable page (`CreateAddress`, `EditAddress`, area/country/state/city edits) overrides `canAccess()` to return `! Resource::isReadOnly()`; the postcode pages omit it. Routes are withheld via `getPages()` when read-only, so this is defense-in-depth inconsistency, not an open hole.
Recommendation: Add the same `canAccess()` override.

9) [low | bug (dormant security note) | src/Support/GuardsAddressingUi.php:1, src/Support/ResolvesAddressingResources.php:1, src/Resources/AddressAreaResource.php:68-71, src/RelationManagers/AddressesRelationManager.php:15 | Dead code incl. an unregistered, owner-unscoped relation manager | high]
Description: `GuardsAddressingUi` and `ResolvesAddressingResources` are never referenced; `AddressAreaResource::getEloquentQuery()` is a no-op passthrough. `AddressesRelationManager` is registered nowhere — and if wired in, its `AttachAction::make()->preloadRecordSelect()` and `EditAction` would operate on owner-scoped `Address` records without any `OwnerUiScope` check (cross-owner attach/edit IDOR).
Recommendation: Delete the unused support types and no-op override; either delete `AddressesRelationManager` or scope its record select/actions with `OwnerUiScope` before registering.

10) [low | bug | src/Resources/AddressCountryResource.php:136-140 | ISO2 accepts 1-char values | high]
Description: `iso2` has `maxLength(2)` but no minimum/`size:2`/alpha rule, so a 1-char code passes (only reachable when `features.country_editing` is enabled; default read-only).
Recommendation: Add `->minLength(2)` (or `size:2` + `alpha`) matching ISO 3166-1 alpha-2.

11) [low | process | packages/filament-addressing/tests (missing) | Package has zero tests | high]
Description: Verified `tests/` does not exist. Owner-scoping, validation rules, importer/exporter wiring, and read-only gating are all untested in-package.
Recommendation: Add Pest coverage at minimum for: owner scoping of Address/Snapshot queries, `canAccess` read-only gating, the two validation rules, and exporter model/scope behavior.

POSITIVES (brief)
- `AddressResource` and `AddressSnapshotResource` correctly apply `OwnerUiScope::apply(..., includeGlobal: false)`; geography resources intentionally global per CONTEXT.
- Submitted IDs revalidated server-side (`StateBelongsToCountry`, `AddressAreasBelongToCountry`) AND core-side (`SyncAddressAreaAssignmentsAction` re-checks country/active; `AddressOwnerGuard::assertAddressIsWritable`).
- Orchestration delegated to core actions (`SaveAddressAreaAction`, `SyncAddressAreaAssignmentsAction`, `ImportAddressAreasAction`); create-path parent validation confirmed present in `SaveAddressAreaAction`.
- Mass assignment safe: `Address::$fillable` excludes `owner_type/owner_id`; `HasOwner@creating` auto-fills owner from `OwnerContext` and fails closed.
- No XSS vectors: no `->html()`/`->markdown()`; `formatStateUsing` outputs plain JSON text; `->url()` targets internal `getUrl()` only.
- No SSRF/path-traversal/deserialization sinks; importer metadata JSON uses `JSON_THROW_ON_ERROR` → `RowImportFailedException`.
- Delete copy matches reality: core `AddressArea@deleting` nulls children's `parent_id`.
- Bounded pagination (`[10,25,50,100]`), search `limit(50)`, request-attribute (Octane-safe) resolver caching, config-driven navigation via `getNavigationGroup`.
- Checked with no findings: N/A correctly — no migrations (no FK question), no money fields, no SoftDeletes, no jobs/commands/widgets/caches to stampede.
