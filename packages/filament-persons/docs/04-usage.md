---
title: Usage
---

# Usage

After registering `FilamentPersonsPlugin`, the configured resources appear in
the navigation group from `filament-persons.navigation.group`.

## Resources

- **Persons** — manage identity records and their names, title assignments,
  credential assignments, and affiliations through relation managers.
- **Titles** — manage title names, categories, usage position, language, and
  ordering.
- **Title issuers** — manage issuer names and issuer types.
- **Credential definitions** — manage credential names, short forms, fields,
  languages, and credential types.

The resource form and table surfaces use the domain relationships supplied by
`aiarmada/persons`. Configure morph aliases and optional country resolution in
the domain package rather than in this adapter.

Title and credential assignments share one form between create and edit:
expiry must fall on or after the awarded/obtained date, and duplicates are
rejected with a validation error. Affiliation institutions resolve from
`persons.models.institution` when configured (owner-aware when the institution
model is owner-scoped).

## Authorization

Every resource gates access through `FilamentPermission` abilities:

| Resource | Ability prefix |
|----------|----------------|
| Persons | `person.*` |
| Titles | `title.*` |
| Title issuers | `title-issuer.*` |
| Credential definitions | `credential-definition.*` |

Each prefix supports `viewAny`, `view`, `create`, `update`, and `delete`.

## Disabling a resource

Disable a resource in `config/filament-persons.php`, then clear cached panel
components if the panel is cached:

```php
'resources' => [
    'enabled' => [
        'credential_definition' => false,
    ],
],
```

The other resource flags may be configured independently.
