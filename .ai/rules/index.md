# Project Rules Index

Before planning or editing, read every rule file whose globs match any path in scope. Apply package-specific rules alongside the shared rules.

| Applies to | Rule file |
| --- | --- |
| packages/addressing/src/Actions/Seed*.php, packages/addressing/src/Commands/Seed*.php, packages/addressing/src/Support/CitySeedRowReader.php, packages/addressing/src/Support/SeedAddressingSummary.php, packages/addressing/database/seeders/**, packages/addressing/config/addressing.php | .ai/rules/addressing-seeding.md |
| packages/addressing/resources/geography/*, packages/addressing/src/Geography/**, packages/addressing/docs/05-country-data.md, packages/addressing/docs/15-locality-expansion.md, tests/src/Addressing/Geography/* | .ai/rules/country-data-expansion.md |
| packages/addressing/resources/geography/malaysia-*, packages/addressing/src/Geography/Malaysia/**, packages/addressing/docs/05-country-data.md, packages/addressing/docs/15-locality-expansion.md, tests/src/Addressing/Geography/Malaysia* | .ai/rules/malaysia-locality-expansion.md |
| packages/commerce-support/** | .ai/rules/platform-core.md |
| packages/** | .ai/rules/features.md |
| packages/*/src/Contracts/**, packages/*/src/Contracts.php, packages/*/src/Data/**, packages/*/src/DTOs/**, packages/*/src/Api/** | .ai/rules/api-contracts.md |
| packages/*/src/**, packages/*/routes/**, demo/** | .ai/rules/host-boundaries.md |
| packages/**, tests/**, demo/**, .ai/** | .ai/rules/verification-and-hygiene.md |
