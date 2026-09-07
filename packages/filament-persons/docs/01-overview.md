---
title: Overview
---

# Filament Persons

`aiarmada/filament-persons` is the Filament v5 administration adapter for
`aiarmada/persons`.

It registers resources for:

- Persons, including names, titles, credentials, and affiliations
- Titles
- Title issuers
- Credential definitions

The package owns only the Filament resources and their panel navigation. The
identity models, migrations, and domain actions belong to `aiarmada/persons`.
The resources do not add tenant scoping because the persons package is a
shared identity layer by design.

The Persons table searches the generated `searchable_name` value together
with the person's name parts, so primary `person_names` values are searchable
without changing the shared identity boundary.

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Persons domain package](../../persons/docs/01-overview.md)
