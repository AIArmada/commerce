---
paths:
  - packages/addressing/resources/geography/malaysia-*
  - packages/addressing/src/Geography/Malaysia/**
  - packages/addressing/docs/05-country-data.md
  - packages/addressing/docs/15-locality-expansion.md
  - tests/src/Addressing/Geography/Malaysia*
---

# Malaysia Locality Expansion

Applies to MY geography rows, postal links, provider metadata, and the
docs/tests covering them.

Shared expansion doctrine — Addressing ownership and consumer delegation,
postcode + official recognition, relocate-don't-delete, and one primary
per postcode with shared codes linked as secondary — lives in
[country-data-expansion.md](./country-data-expansion.md).

MY-specific rules:

- Keep CSV order: alphabetical by slug within the district group,
  postcode links adjacent with the primary first.
- Every expansion ships a sampling test
  (`MalaysiaGeographyProviderTest`), a green
  `GeographyProviderContractTest`, and a `05-country-data.md` update in
  the same pass.
- Method detail:
  `packages/addressing/docs/15-locality-expansion.md`.
