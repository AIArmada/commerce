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
