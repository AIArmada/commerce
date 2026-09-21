---
title: Locality Expansion Method
---

# Locality Expansion Method

How postal-`locality` coverage is grown deliberately, state by state, and
what the Batu Pahat pilot established. The per-state results live in
[Country Data](./05-country-data.md); this file records the method so every
state gets the same treatment.

## What the pilot did (Johor / Batu Pahat)

1. **Cascade truth first.** Postal localities narrowed only within their
   own hierarchy, so a picked district never scoped them. Addressing now
   declares `refinedBy: 'administrative_district'` on the MY postal level;
   `parentAreaIdForRole()`, `successorRoles()`, and
   `effectiveParentLevel()` honor it with link proof and state fallback.
   See [Usage](./04-usage.md).
2. **Consumer delegation.** Ilmu360's `/institusi` locality dropdown was
   hard-scoped to the state. It now gates on addressing's effective parent
   role and scopes options through the resolved parent — no hardcoded
   Johor/Batu Pahat logic.
3. **Town expansion.** External town lists named Tongkang Pechah and Parit
   Yaani with no matching row. Both were added as Batu Pahat `locality`
   rows. They share postcode 83010 with the town core, so they link it as
   **secondary** with Bandar Penggaram staying primary (same precedent as
   Rengam town sharing 86300 with Mukim Renggam).

## Per-state audit procedure

Repeat for every state and WP:

1. List the state's current rows from
   `resources/geography/malaysia-address-areas.csv` by district, plus
   their links in `malaysia-postal-code-areas.csv`.
2. Compare against external town lists (Wikipedia district *Towns*
   sections, postcode directories, council portals). Open every source
   cited; snippets are not evidence.
3. Every town missing from the dataset **entirely** (no row of any type)
   is a candidate. Towns already covered by a mukim/bandar/pekan row are
   not duplicated.
4. Verify each candidate's postcode and parent district from addresses
   and postcode directories before adding.

## Modeling rules

- A new town needs a postcode **and** official recognition (council list,
  postcode directory, gazette-adjacent source). Relocate, don't delete.
- Own postcode → primary link, covering admin rows secondary.
- Shared postcode → secondary link only; the existing primary never moves.
  Invariant: exactly one primary per postcode.
- `source_id` scheme:
  `my:subdistrict:district:{state}:{district}:{slug}`, type `locality`,
  level 3, parent `my:district:{state}:{district}`.
- Keep CSV order: alphabetical by slug within the district group;
  postcode links adjacent with the primary first.

## Verification

- Add a sampling test in
  `tests/src/Addressing/Geography/MalaysiaGeographyProviderTest.php`
  covering each new row (type, level, parent, `postal_locality` role,
  district + state postal links).
- `GeographyProviderContractTest` must stay green (parent references).
- Update the state section in [Country Data](./05-country-data.md) in the
  same pass.
