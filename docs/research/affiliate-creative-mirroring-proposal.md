# Merchant → Network Creative Mirroring — Problems & Proposal

> Status: implemented. All three phases shipped with proving Pest tests;
> open decisions resolved as: program creatives only (D1), network types
> widened with TYPE_IMAGE/TYPE_DOCUMENT (D2), hotlink for mirrored rows +
> local media for manual rows (D3), new `external_creative_id` column (D4),
> reconcile-deletes mirrored rows only (D5), separate `creatives_checksum`
> (D6), active+public gating on both paths (D7), catalog `v2` bump (D8).
> Out-of-scope changes landed alongside: unsigned offer links and widened
> UTM passthrough in `aiarmada/links` — see review notes. This document is
> the decision record.

## Goal

Let `aiarmada/affiliate-network` (marketplace) pull the marketing materials
a merchant manages in `aiarmada/affiliates` (`AffiliateProgramCreative`
rows: banners, links, images, videos, documents, email templates) and
display them to network affiliates alongside the mirrored offers.

## Current state (verified, not assumed)

### Merchant side (`packages/affiliates`)

- `AffiliateProgramCreative` (`src/Models/AffiliateProgramCreative.php`) is
  the marketing-material row: `program_id` (nullable), `type` (free string;
  Filament offers `banner`, `text_link`, `image`, `video`, `document`,
  `email`), `name`, `description`, `width`/`height`, `destination_url`,
  `tracking_code`, `metadata`. No `is_active`, no `sort_order`.
- Post-refactor, files live **only** in the single-file Spatie Media Library
  `creative_asset` collection on the `public` disk. There is no URL column;
  `getAssetUrl()` returns `?string` (null when no file attached). Allowed
  mimes: jpeg/png/gif/webp/svg, mp4/webm, pdf, zip.
- `getEmbedCode(Affiliate)` bakes one affiliate's ref code into the HTML.
  Banner embeds return null without an attached file.
- Owner scoping is program-derived (`ScopesByProgramOwner`).
- `AffiliateProgram::creatives()` is the HasMany; program delete cascades
  per-creative (so media rows/files are removed).

### Network side (`packages/affiliate-network`)

- `AffiliateOfferCreative` (`src/Models/AffiliateOfferCreative.php`) already
  exists: `offer_id` (required), `type` (`banner`/`text`/`email`/`html`/
  `video` constants), `name`, `description`, `url`, `width`/`height`,
  `html_code`, `is_active`, `sort_order`, `metadata`. Owner via
  `offer.site`. No external-id column.
- `AffiliateOffer::creatives()` HasMany exists; offer delete cascades to
  creatives.
- `filament-affiliate-network` has **no** creative relation manager on the
  offer resource (only Legs + Links). Admin display/creation UI is missing.

### The seam between them

Both local and remote reads flow through one method,
`ProgramCatalogService::snapshot()` (`packages/affiliates/src/Services/`):

- Local (shared DB): `MerchantCatalogService::snapshotById` →
  `AffiliatesCatalogReader::snapshot` (runs under the site owner's context).
- Remote: `ProgramCatalogController::show` →
  `GET programs/{id}/catalog` → `RemoteCatalogClient::snapshot`.

The payload (`version: v1`) currently carries `program_id`, `currency`,
`cookie_days`, `base`, `subjects`, `variable_extras`. **No creatives.**
`OfferImportService::syncSubject` mirrors subjects → offers and never
touches `AffiliateOfferCreative`.

## Problems & challenges

1. **Granularity mismatch.** Merchant creatives attach to a *program* (or to
   nothing — general creatives). Network creatives attach to an *offer*
   (= program + subject). One program's creatives must fan out to every
   offer mirrored from that program. General creatives (null `program_id`)
   have no natural target.
2. **Type vocabulary mismatch.** Merchant: `banner`, `text_link`, `image`,
   `video`, `document`, `email`. Network: `banner`, `text`, `email`,
   `html`, `video`. `text_link`→`text` is obvious; `image` and `document`
   have no counterpart. The network enum is closed by convention only
   (string column + constants), so either map or widen.
3. **Embed codes don't transfer.** `getEmbedCode()` personalizes with one
   affiliate's `?aff=CODE`. Mirroring that HTML would misattribute clicks.
   The network must mirror raw asset + destination URLs and build its own
   tracking links (`OfferLinkService`, `?anl=slug`).
4. **Asset reachability.** Post-refactor, assets are Media Library URLs on
   the merchant's `public` disk. Shared-DB installs can hotlink; remote
   installs depend on the merchant's disk staying public. No attachment
   bytes flow through the snapshot. Decide: hotlink (simple, fragile) vs
   copy bytes into network-owned storage (robust, heavy).
5. **No idempotency key on the network row.** `AffiliateOfferCreative` has
   no `external_creative_id`. Re-syncs need a stable match key or they
   duplicate rows. Options: stash merchant id in `metadata` (queryable via
   JSON, clumsy) or add an `external_creative_id` column + migration.
6. **Operator-owned fields must survive re-sync.** The importer already
   protects offer `status`/`visibility`/`slug`/`description` and the rate
   block (rate lock) from mirror overwrites. Creative `is_active` and
   `sort_order` are the analogous operator-owned fields; a naive upsert
   would clobber merchandiser edits on every sync.
7. **Change detection.** `source_checksum` on the offer covers rates/title/
   url/promotions only. Creative edits must either join that checksum
   (re-syncs the offer row too) or get their own sync marker, else edits
   never propagate or every sync rewrites everything.
8. **Deletion propagation.** Merchant creative deleted (or file replaced)
   → mirrored rows go stale. Options: reconcile-per-sync (delete network
   creatives absent from the snapshot), tombstone, or leave orphaned for
   the operator. Note the merchant already hard-deletes media on creative
   delete, so a hotlinked URL 404s immediately.
9. **Visibility gating is inconsistent today.** `mirrorableProgramIds` and
   the remote controller gate on active/public/open, but local
   `snapshotById` fetches any program id. Decide whether creative
   mirroring should enforce active+public on both paths.
10. **Owner boundary on the local path.** The local reader runs under the
    site owner's context and merchant creatives are program-scoped, so a
    site only sees its own owner's programs. Any new read path must stay
    inside that context — no unscoped creative queries.
11. **Null-asset creatives.** `getAssetUrl()` can be null (text links need
    no file). Snapshot and importer must treat asset URL as optional, and
    display must handle file-less rows (portal already does on the
    merchant side).

## Proposal

Three phases, each shippable and testable alone. No new package, no new
top-level seam: reuse the snapshot → import → display pipeline.

### Phase 1 — Emit (merchant, `aiarmada/affiliates`)

- Add `creatives: array<int, array{...}>` to
  `ProgramCatalogService::snapshot()`: merchant creative id, `type`,
  `name`, `description`, `asset_url` (nullable, via `getAssetUrl()`),
  `destination_url`, `width`/`height`, `tracking_code`, `metadata`.
  Eager-load `creatives.media` (one extra query, not N).
- Keep payload additive under `version: v1` (old readers ignore unknown
  keys) unless the team prefers a `v2` bump; record the choice.
- Include program creatives only (decision D1 covers general ones).
- Update `packages/affiliates/docs/07-programs.md` (creatives section) and
  `13-api.md` (catalog payload) in the same pass.
- Tests: snapshot contains creatives with media URLs; null-asset creative
  emits null URL; snapshot stays read-only (no attribution/commission
  rows — assert counts unchanged).

### Phase 2 — Mirror (network, `aiarmada/affiliate-network`)

- In `OfferImportService`, after offer upsert, reconcile that offer's
  creatives from `$snapshot['creatives']`:
  - Match existing rows by external merchant id (D4: metadata key vs new
    column).
  - Map merchant types to network types (D2); keep the original type in
    `metadata['merchant_type']`.
  - Set `url` = merchant asset URL, `html_code` = null (network builds
    its own tracking HTML at display time — never mirror merchant embeds).
  - Never overwrite `is_active`/`sort_order` on update (operator-owned).
  - Delete mirrored rows absent from the snapshot (D5); never touch rows
    the operator created directly (distinguish by absent external id).
- Fold a creative digest into the sync-skip logic (D6) so creative-only
  edits propagate without rewriting offer rows.
- Respect `sync.max_subjects`/`max_programs` caps; count creative
  failures into the existing `failed`/`partial` reporting.
- Update `CatalogReaderInterface`'s `@return` shape and
  `packages/affiliate-network/docs/08-api-reference.md`.
- Tests: fan-out to N offers; re-sync idempotent (no dupes); operator
  `is_active`/`sort_order` preserved; merchant delete removes mirrored
  rows but keeps manual rows; cross-owner site cannot import another
  owner's program creatives.

### Phase 3 — Display (network + Filament)

- Storefront/API: expose `$offer->creatives` with network-generated
  tracking URLs (via `OfferLinkService`), not merchant embed HTML.
- Admin: add a creative relation manager to the offer resource in
  `filament-affiliate-network` (create/edit for manual rows, read-mostly
  for mirrored rows with an "imported" badge), mirroring the merchant's
  `AffiliateCreativeResource` but without the upload-URL duality (upload
  field only, same as the merchant refactor).
- Tests: relation manager lists mirrored + manual; mirrored rows show
  source program; tracking URL uses the network link format.

## Open decisions (for the picking-up agent)

| # | Question | Recommendation |
|---|----------|----------------|
| D1 | General (program-less) merchant creatives: mirror onto every offer, or skip? | Skip in v1; they are portal-scoped, not program-scoped. Revisit if merchants complain. |
| D2 | `image`/`document` type mapping? | `image`→`banner`, `document`→`html`, original in `metadata['merchant_type']`. Alternative: widen network types (touches Filament badges/filters). |
| D3 | Hotlink merchant asset URLs or copy bytes? | Hotlink in v1 (snapshot already URL-based); copy-on-import only if remote reliability becomes an issue. |
| D4 | Idempotency key: `metadata['external_creative_id']` or new column? | New nullable `external_creative_id` + index: the codebase already stashes provenance in real columns (`external_program_id`, `subject_key`), not metadata. |
| D5 | Stale mirrored rows: delete on reconcile or keep? | Delete on reconcile (mirror = truth), but only rows carrying the external id; manual rows are operator property. |
| D6 | Change detection: extend `source_checksum` or separate marker? | Separate `creatives_checksum` on the offer: avoids rewriting rate/audit fields on creative-only edits. |
| D7 | Enforce active+public gating on the local snapshot path? | Yes for the creative read at minimum; consider aligning `snapshotById` wholesale (out of scope but adjacent). |
| D8 | Payload version: additive `v1` or bump to `v2`? | Additive `v1`: `RemoteCatalogClient` only requires `program_id` + `subjects`, so old remotes tolerate the new key. |

## Suggested verification

- `./vendor/bin/pest --parallel tests/src/Affiliates/Unit/<touched>` and
  the affiliate-network suites covering `OfferImportService` + new
  relation manager tests.
- `./vendor/bin/pint --test` on changed files only (never repo-wide).
- `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6` per touched
  package.
- Cross-tenant regression test on both the emit side (local reader under
  site owner) and the mirror side (import writes stay site-scoped).

## Key references

- Merchant model: `packages/affiliates/src/Models/AffiliateProgramCreative.php`
- Merchant program relation + cascade: `packages/affiliates/src/Models/AffiliateProgram.php` (`creatives()`, `booted`)
- Snapshot builder (single seam): `packages/affiliates/src/Services/ProgramCatalogService.php`
- Local seam impl: `packages/affiliates/src/Services/MerchantCatalogService.php`
- Seam contract: `packages/affiliates/src/Contracts/MerchantCatalog.php`
- Remote endpoint: `packages/affiliates/src/Http/Controllers/ProgramCatalogController.php`
- Network reader: `packages/affiliate-network/src/Adapters/Affiliates/AffiliatesCatalogReader.php`
- Remote client: `packages/affiliate-network/src/Services/Catalog/RemoteCatalogClient.php`
- Reader contract: `packages/affiliate-network/src/Services/Catalog/CatalogReaderInterface.php`
- Importer: `packages/affiliate-network/src/Services/OfferImportService.php`
- Network models: `packages/affiliate-network/src/Models/AffiliateOfferCreative.php`, `AffiliateOffer.php` (`creatives()`)
- Tracking links: `packages/affiliate-network/src/Services/OfferLinkService.php`
- Merchant admin reference: `packages/filament-affiliates/src/Resources/AffiliateCreativeResource.php`
- Cross-package map: `docs/affiliates.md`
