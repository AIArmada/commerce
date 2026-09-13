E2E review: filament-organizations, products, filament-pricing, references (workspace /Users/Saiffil/Herd/commerce). Read-only via search+read. No package-local tests/ dirs; central Pest suites at tests/src/Products, tests/src/References used as contract context.

POSITIVES (brief): uuid PKs everywhere (HasUuids); no DB FK constraints/cascades (foreignUuid unconstrained) and no SoftDeletes — matches repo rules; money as int minor units (unsignedBigInteger, ProductPricing::formatMinor); owner scoping via commerce-support with fail-closed OwnerScope (throws without context); variant generation cap + queue threshold; simulator/RM searches limit(50) and mostly owner-scoped; Reference parent owner guard (OwnerWriteGuard) + cycle-safe subtree collect; Organization list scoped to member orgs + authorizeRecord on mutations; no Octane-unsafe static state found; no raw SQL/user-input into raw queries (only static whereRaw('1 = 0')).

=== PRODUCTS (domain) ===
P1 — high / bug — src/Models/Product.php:817 + src/Models/Option.php:218-219 — Mass deletes skip model events, orphaning OptionValues and variant pivots. Evidence: `$product->options()->delete();` is a query-builder mass delete (no deleting events), so `Option::deleting` never runs; and `Option::deleting` itself does `$option->values()->delete()` (mass delete), so `OptionValue::deleting` (`$optionValue->variants()->detach()`) never runs. Result: orphaned option_values rows and orphaned product_variant_options pivot rows on every product/option delete (variants handled correctly via `->each(delete)` at Product.php:815). Recommend: `each(fn($o)=>$o->delete())` in both places. Confidence: high.

P2 — high / bug — src/Strategies/MatrixVariantGenerator.php:94-99 — SKU dedup ignores product_id, leaking cross-product variants. Evidence: `Variant::query()->forOwner($owner,false)->where('sku',$sku)->first()` — no `where('product_id',...)`. If two same-owner products share a parent SKU (nullable + no prod unique index, see P3), regeneration for product B returns/attaches product A's variant and skips creation. Recommend: add product_id constraint. Confidence: high.

P3 — high / bug+performance — database/migrations/2001_01_01_000001*:84-89, 000004*:58-62, 000012*:39-43 (same pattern in others) — Identity unique indexes only created in local/development/testing. Evidence: `if (! app()->environment(['local','development','testing'])) return; ProductIdentityIndexes::owner(...)`. Production has NO slug/sku uniqueness and NO slug/sku indexes: app-level `EnforcesOwnerUniqueIdentity` check is TOCTOU-racy (concurrent duplicates), and slug route binding + uniqueness probes full-scan. Recommend: always create partial unique indexes (all envs). Confidence: high.

P4 — high / bug (DoS) — src/Models/Category.php:303-361,435-476 — No parent validation (no cycle/self check, no owner check on parent_id) + unguarded recursion. Evidence: booted() validates only owner columns; `getAncestors()` (`while ($category->parent !== null)`) has no visited set; `getNestedTree()` recurses via lazy `children`. Any writer can create A↔B cycle (or self-parent) → infinite loop / stack overflow in getAncestors/getDepth/getFullPath/getFullSlug/getNestedTree. Recommend: validate parent (exists, same owner, not self/descendant) in saving hook. Confidence: high.

P5 — medium / bug — src/Actions/CreateProduct.php:18-20, src/Actions/UpdateProduct.php:22-25, src/Models/Product.php:156-160 — Double domain-event dispatch. Evidence: model `$dispatchesEvents = ['created'=>ProductCreated,'updated'=>ProductUpdated,...]` AND actions explicitly `ProductCreated::dispatch($product)` / `ProductUpdated::dispatch($fresh)` (different instances). Subscribers (indexing, notifications) run twice. Recommend: remove explicit dispatch, rely on model events. Confidence: high.

P6 — medium / security — src/Models/Collection.php:419-439 — Automatic-collection conditions allow arbitrary column/operator from fillable JSON. Evidence: `default => $query->where($field, $operator, $value)` with `$field/$operator/$value` from `conditions` (fillable, unvalidated). Column names are grammar-escaped (not SQLi) but any writer can probe arbitrary columns (cost, metadata) and invalid operators throw 500s. Mitigated to own tenant by owner scoping. Recommend: allowlist fields/operators. Confidence: high.

P7 — medium / performance — src/Models/Collection.php:245-278 — Unbounded `get()` + `sync()` in getMatchingProducts/rebuildProductList. Large automatic collections load all matching products into memory and sync huge pivot sets. Recommend: chunk/cursor + syncWithoutDetaching or batched sync. Confidence: high.

P8 — medium / bug — src/Actions/ApplyAttributeChanges.php:39-70 — Bypasses type serialization, silent no-ops, nullable fresh(). Evidence: `update(['value'=>$value])` raw (vs `setCustomAttribute` → `serializeValue`); array/date values corrupt to "Array"; unknown codes silently skipped; `$product->fresh()` may be null against non-nullable return → TypeError. Recommend: reuse setCustomAttribute path, error on unknown codes, handle null fresh. Confidence: high.

P9 — medium / security — src/Models/Product.php:405-439, src/Models/Variant.php:245-250, migration string(3) currency — currency fillable with no allowlist → dynamic `Money::$currency(...)` crash. Any writer sets currency='XX' → formatting/money accessors throw (500). Recommend: ISO-4217 allowlist validation + enum/cast. Confidence: high (crash type depends on akaunting/money version: med).

P10 — medium / security — src/Models/AttributeValue.php:186-200 — attributable_type accepts ANY Model class. Evidence: `class_exists + is_a(Model)` then queries it; only Product/Variant intended. Lets writers attach attribute rows to User/Order/etc (junk rows, cross-model mixing, id existence oracle). Recommend: allowlist [Product::class, Variant::class]. Confidence: high.

P11 — medium / performance — N+1 cluster — Variant.php:213-240,331-354 (product/optionValues per call: getDisplayImagesAttribute, getEffectivePrice, getOptionSummary, getFullName), Product.php:690-718 (getStockQuantity fans out per-variant inventory calls), Category.php:370-395 (getProductCount/getAllProducts recurse one query per category + in-memory merge/unique). Recommend: eager-load in callers,皆 bulk inventory API, iterative+cached trees. Confidence: high.

P12 — low / bug — src/Models/Variant.php:381-387 — Inventory outage swallowed → false out-of-stock. Evidence: `catch (Throwable) { return 0; }` while Product falls back to local stock. Recommend: propagate or fail-open consistently + log. Confidence: high.

P13 — low / bug — src/Concerns/EnforcesOwnerUniqueIdentity.php:39-90 — App-level uniqueness is racy and fabricates `UniqueConstraintViolationException` with synthetic SQL on create (may confuse retry/alert handlers). Moot once P3 fixed. Confidence: med.

P14 — low / performance — database/factories/ProductFactory.php:53 — `Schema::getColumnListing()` per factory definition (schema query per product in seeds/tests). Recommend: cache columns statically per process. Confidence: high.

P15 — low / security — src/Jobs/GenerateVariantsJob.php:29-41 — performJob uses `withoutOwnerScope()->whereKey(productId)` without verifying product vs job owner context (payload tamper → cross-tenant generation attempt; currently fails closed via Variant creating guards → job-failure/retry nuisance). Recommend: `OwnerWriteGuard::findOrFailForOwner`. Confidence: med.

P16 — low / security — src/Models/OptionValue.php:164-175 — getSwatchStyle interpolates unvalidated `swatch_color`/`swatch_image` into CSS (`background-color: {...}`, `url('{...}')`); stored CSS-breakout/XSS if rendered raw (`{!! !!}`). Recommend: validate color hex + URL-encode/escape at render. Confidence: med (no raw-render call site found in scope).

=== FILAMENT-PRICING (adapter) ===
F1 — high / security — src/Widgets/PricingStatsWidget.php:19-21 — PriceList count unscoped (cross-tenant leak). Evidence: `PriceList::query()->active()->count()` with no forOwner, while promotions query just below IS owner-scoped. Every tenant sees global active-list count. Recommend: mirror promotion scoping. Confidence: high.

F2 — high / security — src/Resources/PriceListResource/RelationManagers/PricesRelationManager.php:103-138 — Arbitrary model class from user input + no save-time revalidation. Evidence: `getOptionLabelUsing` accepts any `$type` with `class_exists + is_a(Model)` then `$type::query()` (TiersRelationManager:118 correctly allowlists Product/Variant — inconsistency confirms bug); Create/Edit actions never revalidate priceable_type/id server-side, so prices attach to arbitrary models. Recommend: allowlist + OwnerWriteGuard revalidation in mutateFormDataUsing/before hooks. Confidence: high.

F3 — high / security — src/Pages/ManagePricingSettings.php:154-177 — Global pricing settings mutable by any panel user, validation bypassed. Evidence: `save()` reads raw `$this->data` (never `$this->form->getState()`), so min/max rules (e.g. decimalPlaces 0-4) unenforced; no `canAccess`/policy; Spatie settings are global → one tenant's user changes currency/rounding/min-max for ALL tenants. Recommend: admin-only authorization + validated state + per-owner settings or explicit global-only guard. Confidence: high.

F4 — medium / security — src/Pages/PriceSimulator.php:346-438 — calculate() uses unvalidated raw state. Evidence: `$data = $this->data ?? []`; direct `$data['product_type']`, `(int)$data['quantity']` (0/negative/huge/"abc"→0), raw `effective_at` into calculator. Form rules (required/minValue) bypassed via direct Livewire call. Recommend: `$this->form->getState()` + quantity bounds. Confidence: high.

F5 — medium / security — src/Resources/PriceListResource/Schemas/PriceListForm.php:37-41 — slug `unique(ignoreRecord:true)` is global, not owner-scoped → cross-tenant slug blocking + existence oracle. Recommend: owner-scoped unique rule (or per-owner partial index + scoped rule). Confidence: high.

F6 — low / security — LIKE wildcard injection (unescaped %/_ in search) → over-broad matches/DoS: PriceSimulator.php:119,183,286; PricesRelationManager.php:69,92; TiersRelationManager.php:75,98. Recommend: escape LIKE specials. Confidence: high.

F7 — low / performance — TiersRelationManager.php:217-227 — table state closures `loadMissing('tierable')` (+product) per row → 2N queries/page. Recommend: eager-load in table query. Confidence: high.

F8 — low / bug — Null-unsafe `$v->product->name` on possibly-orphaned variants (500), inconsistent with PriceSimulator's `?->`: PricesRelationManager.php:97,134; TiersRelationManager.php:103,139. Recommend: null-safe + fallback. Confidence: high.

F9 — low / security — No policies in pricing package (search found zero Policy/Gate in pricing/src), so PriceList/Price/Tier CRUD relies on Filament defaults. Recommend: add owner-aware policies like products. Confidence: med.

=== REFERENCES (domain) ===
R1 — high / security — database/migrations/2000_01_01_000001*:21 — slug globally unique in multi-tenant model. Evidence: `$table->string('slug')->unique()`. Cross-tenant slug squatting/blocking + existence oracle; contradicts owner-scoping. Recommend: drop global unique, add per-owner (+global) partial uniques. Confidence: high.

R2 — medium / bug — same migration (whole file) — no `down()` method; rollback leaves table behind. Recommend: add dropIfExists. Confidence: high.

R3 — medium / bug — src/Models/Reference.php:95-122,235-260,80-93 — custom delete() gaps. (a) descendants mass-deleted without child model events (no per-child deleting/deleted observers); (b) media cleanup `->get()->each(delete)` unbounded (memory on huge subtrees); (c) `collectSubtreeIds()` uses scoped `static::query()` so children outside current scope (e.g. owned children of a global parent) are orphaned, not deleted; (d) saving guard checks parent ownership but not self-parent/cycles. Recommend: chunked deletes with events (or documented mass-delete), chunked media delete, scope-aware subtree collection, parent≠self/descendant validation. Confidence: high (a,b,d); med (c — depends on global+owned mixing).

R4 — medium / security — No Policy/Gate for Reference (service provider registers none) vs products' 10 policies — auth left to consumers. Recommend: ship owner-aware ReferencePolicy. Confidence: med.

R5 — low / security — src/Models/Reference.php:59-76 — Validation gaps: unbounded JSON (reference_parts/metadata → oversized-payload DoS), year/isbn/url/language unvalidated (negative year, non-URL, overlong). No Action layer. Recommend: FormRequest/Action validation. Confidence: high.

R6 — low / bug — src/Models/Reference.php:124-127 vs config — getTable() default 'ref_references' mismatches config default 'references' (stale fallback when config unloaded). Recommend: align defaults. Confidence: high.

R7 — low / bug — Reference owns `reference_parts` but doesn't use `HasReferenceParts` trait (helpers only tested on a stub model) — DX inconsistency; consumers hand-roll JSON. Recommend: use trait on Reference or document. Confidence: high.

Positive note: Reference fillable correctly EXCLUDES owner_type/owner_id (secure default, unlike products models which mass-assign owner + rely on guards) — keep, and consider aligning products.

=== FILAMENT-ORGANIZATIONS (adapter) ===
O1 — medium / bug — Pages/EditOrganization.php:16-24 + Resources/OrganizationResource.php:76-78 — Direct `fill($data)->save()` bypasses domain action; slug editable on edit with no unique rule → duplicate slugs, bypasses domain invariants (form limits mass-assignment to 3 fields, so contained). Recommend: domain UpdateOrganizationAction + unique rule. Confidence: high.

O2 — medium / security — RelationManagers/InvitationsRelationManager.php (whole) — No revoke/resend/delete row actions though `membership/.../RevokeInvitationAction` exists → stale invitations irrevocable from UI. Recommend: add revoke action (authorize organization.manage-members). Confidence: high.

O3 — low / security — MembersRelationManager.php:45-46 — addMember email existence oracle (422 'User not found' vs success) + exact-match lookup (collation-dependent case behavior). Recommend: generic message + normalized lookup. Confidence: med.

O4 — low / security — MembersRelationManager.php:60,67 — Owner-role protection is `visible()`-only in UI; server enforcement delegated to membership actions (Organization::assertMemberCanBeAdded seen blocking owner demotion — defense-in-depth OK if Remove/Change actions enforce). Recommend: assert/verify in action closures. Confidence: med.

O5 — low / performance — Pages/ViewOrganization.php:100-106 — transfer-ownership options `->get()` all members unbounded. Recommend: searchable async select. Confidence: high.

O6 — low / security — Resources/OrganizationResource.php:42-45 — any authenticated user can create orgs (canCreate=true); ensure intended + rate-limit to prevent org-creation spam. Confidence: med.

Unresolved/none material: XSS/SSRF/path-traversal/deserialization — no sinks found in scope (no unserialize/eval/shell, no URL fetching, no file-path building from input, blade uses escaped `{{ }}`); cache stampedes N/A (no caching); Octane state clean.
