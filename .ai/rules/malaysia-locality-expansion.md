# Malaysia Locality Expansion

Applies to MY geography rows, postal links, provider metadata, and the
docs/tests covering them.

- Addressing owns cascade, label, and parent truth. Consumers query
  `CountryAddressProfileResolver`; never hardcode state/district scoping.
- New postal towns require a postcode **and** official recognition.
  Relocate, don't delete.
- Exactly one primary per postcode. Shared postcodes link new towns as
  secondary; the existing primary never moves.
- Keep CSV order: alphabetical by slug within the district group,
  postcode links adjacent with the primary first.
- Every expansion ships a sampling test
  (`MalaysiaGeographyProviderTest`), a green
  `GeographyProviderContractTest`, and a `05-country-data.md` update in
  the same pass.
- Method detail:
  `packages/addressing/docs/15-locality-expansion.md`.
