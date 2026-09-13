End-to-end review: filament-products, organizations, filament-seating, filament-persons. All findings below were verified by reading the cited file bodies (plus referenced domain models in products/seating/persons/pricing and commerce-support owner primitives). No routes/ or tests/ directories exist in any of the four packages (verified by scoped search returning zero matches).

## filament-products

1. severity: high | category: bug | packages/filament-products/src/Resources/CategoryResource/Pages/EditCategory.php:25 — Self-parent / hierarchy cycle allowed, causes infinite loop
Description: mutateFormDataBeforeSave only checks the submitted parent_id is owner-scoped; nothing rejects parent_id == the record's own id or a descendant's id. CreateCategory (same, :17) has the same gap. products Category::getAncestors() (packages/products/src/Models/Category.php:303) walks `while ($category->parent !== null)` with no cycle guard, so a self-parent loops forever (hung PHP/Octane worker). Every row of the category table triggers this via getDepth() (CategoriesTable.php:33-38).
Evidence: `if (isset($data['parent_id']) && is_string(...)) { $allowed = OwnerScopedIds::allowedIds(...); if ($allowed === []) unset(...); }` — no self/descendant check.
Recommendation: reject parent_id equal to the record id and any current descendant (Filament `different:`/custom rule + server-side check in both pages); add a defense-in-depth cycle guard in products Category model (domain owner).
Confidence: high.

2. severity: medium | category: bug/performance | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:441 — CSV import unbounded, synchronous, silently coerces bad data
Description: FileUpload has no maxSize (:147-153); importProducts has no row cap, runs synchronously in the request, does one SKU lookup + save per row with no batching/queue/transaction. Invalid enums silently coerce (`ProductStatus::tryFrom(...) ?? Draft`, :484-486), missing price defaults to 0 (:480) — corrupt CSV rows create free/draft products instead of erroring.
Evidence: `$csv = Reader::createFromPath($filePath, 'r'); ... foreach ($records as $offset => $record)` with per-row `Product::query()->forOwner($owner, false)->where('sku', ...)->first()` then `->save()`.
Recommendation: add maxSize + max row limit, queue/chunk the import, wrap batches in transactions, and surface invalid enum/currency/price cells as row errors instead of defaulting.
Confidence: high.

3. severity: medium | category: performance | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:545 — Export loads entire catalog into memory; streaming is fake
Description: `$query->get()` materializes every product, then `Writer::createFromString()` builds the whole CSV string before `streamDownload` echoes it — OOM risk on large catalogs despite the StreamedResponse wrapper.
Evidence: `$products = $query->get(); $csv = Writer::createFromString(); ... echo $csv->toString();`
Recommendation: cursor/chunk the query and stream rows incrementally (`Writer::createFromPath('php://output')` or chunked echo with flush).
Confidence: high.

4. severity: medium | category: security | packages/filament-products/src/Resources/ProductResource/Schemas/ProductForm.php:53 — Global unique() checks leak cross-owner slugs/SKUs and allow squatting
Description: `TextInput slug/ska ->unique(ignoreRecord: true)` (also CategoryForm.php:42, AttributeSetForm.php:27, VariantsRelationManager.php:47) issues table-level uniqueness checks that ignore owner scope, while the domain enforces uniqueness per-owner (Product::EnforcesOwnerUniqueIdentity on slug/sku). Effects: another owner's slug/SKU blocks creation (availability), and "already taken" responses let one tenant enumerate other tenants' slugs/SKUs.
Recommendation: scope the unique rule to the current owner tuple (modifyRuleUsing / Rule::unique()->where(owner_type, owner_id)).
Confidence: med (high that the check is global; impact depends on Filament unique-rule internals, which are table-based).

5. severity: medium | category: security | packages/filament-products/src/Resources/ProductResource/RelationManagers/PricesRelationManager.php:39 — price_list_id never revalidated; cross-owner linkage possible
Description: PriceList is HasOwner (verified), the create/edit Select has no owner scoping and neither CreateAction (which only sets priceable_type/id, :170-178) nor EditAction calls OwnerScopedIds — a tampered price_list_id links a product to another owner's price list, unlike every other ID field in this package.
Recommendation: add `OwnerScopedIds::ensureAllowed('price_list_id', PriceList::class, ...)` in mutateFormDataUsing for create and edit, and scope the relationship select.
Confidence: high.

6. severity: medium | category: performance | packages/filament-products/src/Widgets/TopSellingProductsWidget.php:70 — Per-row queries (N+1) in tables/widgets
Description: variants_count column calls getVariantsCount() per row (:70-73, :91-97: `$record->variants()->count()`); ProductsTable price description does 2 counts per row (:86-102); CategoriesTable name formatter walks ancestors per row (:33-38, each level a lazy query).
Recommendation: withCount('variants'), eager price counts, and eager-load/cache category depth (or denormalize depth).
Confidence: high.

7. severity: medium | category: security | packages/filament-products/src/Resources/CategoryResource.php:1 — Only ProductResource has authorization; sibling resources and bulk actions have none
Description: ProductResource defines canViewAny/canView/canCreate/canEdit/canDelete + shouldRegisterNavigation via FilamentPermission, but Category/Collection/Attribute/AttributeGroup/AttributeSet resources define zero can* methods, and CategoriesTable bulk show/hide/delete (:112-131) carry no authorize() calls. Any panel user can mutate catalog taxonomy.
Recommendation: mirror ProductResource's FilamentPermission gates (or policies) on all resources and authorize bulk actions.
Confidence: med (host panel may layer its own auth, but the package is inconsistent with its own pattern).

8. severity: low | category: bug | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:256 — Duplicate uses time() slug and copies only the shell
Description: `'slug . '-copy-' . time()` collides if duplicated twice in one second (then surfaces as a save exception); variants/options/prices/media/categories are not copied despite the "Duplicate" label.
Recommendation: loop a unique slug (or reuse domain slug logic) and either copy relations or rename the action to "Duplicate as new shell".
Confidence: high.

9. severity: low | category: bug | packages/filament-products/src/Resources/ProductResource/Tables/ProductsTable.php:336 — Bulk price update: float math, no transaction, partial failure
Description: per-record `$product->price / 100` float round-trip with individual updates outside a transaction — a mid-batch failure leaves half the selection repriced; `$data['value']` has no upper bound.
Recommendation: integer minor-unit math, wrap in a transaction, cap value.
Confidence: high.

10. severity: low | category: bug | packages/filament-products/src/Support/ProductStatsAggregator.php:52 — Widgets silently degrade to global-only stats when owner context is missing
Description: withResolvedOwnerOrExplicitGlobal() wraps every widget query in OwnerContext::withOwner(null) when unresolved, so a misconfigured tenant dashboard shows global-record stats instead of failing closed. Not a cross-tenant leak (forOwner(null) scopes to ownerless rows), but masks configuration errors.
Recommendation: fail closed (or show an explicit "no owner context" state) instead of silent global fallback.
Confidence: high.

## organizations

11. severity: medium | category: bug | packages/organizations/src/Actions/CreateOrganizationAction.php:29 — No input length/type validation; oversize input 500s
Description: handle() only mb_trims name and checks non-empty; name >255 chars or a non-string description blows up as a raw QueryException instead of a validation error.
Recommendation: validate name (required, string, max:255), slug format, description nullable|string before the transaction.
Confidence: high.

12. severity: medium | category: bug | packages/organizations/database/migrations/2000_01_01_000001_create_organizations_table.php:1 — Migrations have no down(); rollback/refresh broken
Description: both migrations define only up(). migrate:rollback is a silent no-op and migrate:refresh leaves tables behind so re-migration fails with "table exists".
Recommendation: add down() dropping the configured table names.
Confidence: high.

13. severity: medium | category: bug | packages/organizations/src/Actions/MakeOrganizationPublicAction.php:25 — No state-machine guards; restore can silently re-publish
Description: a suspended/archived org can be made Public (published_at set while inactive), and RestoreOrganizationAction (:25-38) flips status to Active without any visibility review — restoring a suspended+public org re-publishes it with no visibility transition audit/authorization.
Recommendation: block visibility transitions unless Active (or require explicit visibility re-confirmation on restore).
Confidence: high.

14. severity: low | category: security | packages/organizations/src/Resolvers/DefaultOrganizationAuthorization.php:30 — Unknown abilities default-allow for Owner/Admin
Description: `default => in_array($role, [Owner, Admin])` fails open for any future/typo'd ability string. Prefer fail-closed (deny unknown).
Confidence: high.

15. severity: low | category: bug | packages/organizations/src/Actions/TransferOrganizationOwnershipAction.php:44 — New-owner model type not verified
Description: the target lookup matches only by key (`whereKey($newOwner->getKey())`); a different model class with a colliding key would pass the membership check.
Recommendation: assert the new owner is the expected member model type (and same morph class).
Confidence: med.

16. severity: low | category: security | packages/organizations/src/Models/Organization.php:48 — $fillable includes status/visibility/lifecycle timestamps
Description: within this package CreateOrganizationAction allowlists its fields, so no direct exploit here; but fillable status/visibility/created_by invites host-code mass-assignment (`Organization::create($request->all())`).
Recommendation: narrow $fillable and use explicit assignment/forceFill in actions.
Confidence: high (on the surface; low on exploitability in-package).

## filament-seating

17. severity: medium | category: bug | packages/filament-seating/src/Widgets/SeatMapOverview.php:15 — Widget throws (dashboard 500) with no owner context
Description: `SeatMapModel::count() / Seat::count()` rely on the implicit OwnerScope global scope, which throws NoCurrentOwnerException when no owner is resolved (verified in OwnerScope.php:23). No try/catch, no forOwner(), unlike the products widgets. (Not a leak — the scope applies — but an availability bug.)
Recommendation: resolve the owner explicitly and use forOwner()/OwnerUiScope like SeatMapResource::getEloquentQuery does.
Confidence: high.

18. severity: medium | category: security | packages/filament-seating/src/Resources/SeatMapResource.php:1 — No authorization on the resource
Description: no can* methods, no policy checks on view/edit actions; any authenticated panel user can read/edit all seat maps in scope. Inconsistent with ProductResource's FilamentPermission pattern.
Recommendation: add canViewAny/canView/canCreate/canEdit/canDelete gates.
Confidence: med (host-dependent, pattern-inconsistent).

19. severity: medium | category: bug | packages/filament-seating/src/Pages/SeatMapEditor.php:19 — Editor/occupancy pages are dead yet registered; seatMapId unvalidated
Description: canAccess() returns false on both pages while the plugin still registers them; mount(?string $seatMapId) accepts any id with zero owner/existence validation and passes it to the Livewire seat-map component — an IDOR waiting to happen if the pages are ever enabled.
Recommendation: either remove the pages or implement canAccess + owner-scoped seatMapId resolution (OwnerUiScope::findForRecordOwner).
Confidence: high.

20. severity: low | category: bug | packages/filament-seating/src/Resources/SeatMapResource.php:48 — Weak form validation; status not enum-bound
Description: slug optional and non-unique, version numeric with no min (negatives allowed), status hardcoded strings while the model stores a plain string — drift-prone.
Recommendation: require/unique-scope slug, minValue(1) on version, back status by an enum with casts.
Confidence: high.

## filament-persons

21. severity: medium | category: bug | packages/filament-persons/src/Resources/PersonResource/RelationManagers/TitleAssignmentsRelationManager.php:79 — All relation-manager EditActions have empty forms
Description: Title/Credential/Names/Affiliations managers define ->form([...]) only on CreateAction; EditAction::make() is bare and no form(Schema) method exists, so every Edit modal renders with no fields — assignments cannot be edited. (CredentialAssignments :72, Names :65, Affiliations :94 identical.)
Recommendation: extract shared form schemas used by both create and edit actions.
Confidence: high.

22. severity: medium | category: bug | packages/filament-persons/src/Resources/PersonResource/RelationManagers/AffiliationsRelationManager.php:30 — Institution selection is a non-functional stub
Description: getInstitutionOptions() hardcoded to `[]`, so the institution Select is always empty; the table falls back to rendering raw institution ids.
Recommendation: resolve real options from ModelResolver::institutionClass() (owner-aware where applicable) or remove the field until implemented.
Confidence: high.

23. severity: medium | category: security | packages/filament-persons/src/Resources/PersonResource.php:1 — No authorization on global identity/PII resources
Description: Person/Title/TitleIssuer/CredentialDefinition resources define no can* gates; persons are deliberately global (names, DOB, biographies) so every panel user gets full read/write on shared identity data.
Recommendation: add FilamentPermission/policy gates consistent with filament-products.
Confidence: med (host-dependent, but PII-adjacent globals deserve in-package gates).

24. severity: low | category: bug | packages/filament-persons/src/Resources/PersonResource/RelationManagers/TitleAssignmentsRelationManager.php:60 — Assignment validation gaps; inconsistent institution guard
Description: create only findOrFail()s the foreign id — no date_awarded<=date_expired check, no duplicate (person,title/credential) guard; TitleIssuerResource institution_id (:65-76) is free text with no PersonsModelReferenceGuard check even though Affiliations create (:62-74) does guard it.
Recommendation: add date-order + uniqueness validation and guard institution_id consistently.
Confidence: med.

25. severity: low | category: performance | packages/filament-persons/src/Resources/TitleResource.php:78 — Sort-order helper queries per form render; null-category logic wrong
Description: the live helperText closure runs an exists() query on every re-render, and `->where('category_id', null)` never matches (should be whereNull), so guidance is wrong until a category is picked.
Recommendation: skip the query when category/position are blank and debounce/cache.
Confidence: high.

26. severity: low | category: performance | packages/filament-persons/src/Resources/PersonResource/RelationManagers/NamesRelationManager.php:56 — Uncached language list per form render
Description: `Language::query()->orderBy('name')->pluck(...)` executes on every Names form open with no caching.
Recommendation: cache the small languages map with a TTL.
Confidence: high.

## Cross-cutting

27. severity: medium | category: bug | packages/{filament-products,organizations,filament-seating,filament-persons} — Zero tests in all four packages
Description: verified by scoped search — no tests/ directories or Pest suites anywhere in these packages, so none of the above (owner scoping, transfers, imports) is regression-protected.
Recommendation: add Pest coverage at minimum for owner-scope enforcement, organization transfer invariants, and CSV import/export round-trips.
Confidence: high.

## Positives (brief)
- Owner scoping in filament-products is thorough: forOwner() on every resource query, OwnerScopedIds::ensureAllowed in all Create/Edit pages, OwnerQuery scoping on selects/filters/bulk category assignment, OwnerUiScope on SeatMapResource.
- Organizations invariants are solid: exactly-one-owner enforced transactionally with lockForUpdate, immutable created_by, race-safe slug retry, fail-closed middleware + resolver boot check.
- CSV path traversal guarded (imports/ prefix + Storage::exists); no SSRF/deserialization/XSS sinks found (no Http/file_get_contents/unserialize/eval/RawHtml in these packages); stats cache invalidation correctly wired via model events; money consistently int minor units; uuid PKs, no FK constraints, no SoftDeletes, no static mutable state (Octane-safe).