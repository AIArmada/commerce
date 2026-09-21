# Country Data Expansion

Applies to every country geography dataset, provider, and the docs/tests
covering them. Malaysia was the first worked example; new countries follow
the same doctrine.

- Addressing owns cascade, label, and parent truth. Consumers delegate
  through `CountryAddressProfileResolver`; never hardcode state or
  district scoping in an app.
- Sweep one top-level division at a time (state/province/WP): list its
  current rows by district plus their postal links, then compare against
  external town lists. Open every cited source; snippets are not evidence.
- A town missing from the dataset **entirely** (no row of any type) is a
  candidate. Towns already covered by an administrative row are not
  duplicated as localities.
- Verify each candidate's postcode and parent district from addresses and
  postcode directories before adding.
- A new town needs a postcode **and** official recognition (council list,
  postcode directory, gazette-adjacent source). Relocate, don't delete.
- Exactly one primary per postcode. Shared postcodes link as secondary;
  the existing primary never moves.
- Cross-hierarchy narrowing must be declared (`refinedBy`) with link proof
  and a broader-scope fallback. The fallback is by design where a district
  genuinely has no localities — but it also masks stale databases (below).
- Grouped subdivision/locality presentation defaults live in addressing
  config; consumers honor the default and override only through config.
- Follow each file's existing row and link ordering convention; keep the
  provider's `source_id` scheme stable.
- Dataset truth is not database truth: after any CSV or provider change,
  consumers must re-run `address:seed`. The stale-seed symptom is
  over-broad scoping (a district page listing other districts' towns).
- Every expansion ships a sampling test for the new rows, a green
  `GeographyProviderContractTest`, a `05-country-data.md` update, and a
  consumer reseed, in the same pass.
- Method detail:
  `packages/addressing/docs/15-locality-expansion.md`.
