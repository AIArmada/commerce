---
title: Persons Overview
---

## Introduction

`aiarmada/persons` is a reusable person identity package for Laravel. It provides a normalized identity layer — a canonical `persons` table plus relational systems for multi-context names, titles, credentials, and affiliations. Assignment tables are polymorphic so any model can participate as a titleable, credentialable, or affiliatable.

## What this package owns

- The canonical `persons` identity table
- Multi-context, multi-language `person_names`
- Titles, title categories, and title issuers
- Polymorphic title assignments (`titleable`)
- Credential definitions and polymorphic credential assignments (`credentialable`)
- Affiliations and the roles held within them
- Formatted display-name composition from ordered title assignments

## Media

`spatie/laravel-medialibrary` is optional. The base `Person` model does not use media collections. Host applications that need media support should extend `Person` and add `HasMedia` / `InteractsWithMedia` on their subclass.

## What this package does not own

- Event involvement or event roles — those belong to `aiarmada/events`. A person is linked to an event through the events package's polymorphic `involveable` junction, wired at the application layer.
- Institutions/organizations — `affiliations.institution_id` is a loose UUID resolved to a host application model via `persons.models.institution`.
- Users, members, donations, reports, share tracking, or Filament admin surfaces.
- Country data — `nationality_country_id`, `title.country_id`, and `title_issuers.country_id` are loose UUIDs; relations into `aiarmada/addressing` are optional and wired at the application layer.

## Core Concepts

| Concept | Description |
|---|---|
| **Person** | Canonical identity record (renamed concept from a host's `Speaker`). |
| **Person Name** | Multi-context name variant (legal, display, religious, etc.) with a language code. |
| **Title** | A reference definition (e.g. "Datuk", "PhD") with a category, usage position, and sort order within that category. |
| **Title Assignment** | Polymorphic junction linking any model to a title, with status and dates. |
| **Credential** | A definition (e.g. "Doctor of Philosophy") plus a polymorphic assignment with issuing details. |
| **Affiliation** | Polymorphic link between any model and an institution, with a type and roles. |
| **Formatted Name** | Composed from a person's display name plus ordered before/after titles. |

## Sort order behavior

Formatted names use three levels of ordering:

1. `usage_position` separates titles before and after the name.
2. `title_categories.sort_order` orders category groups within each position.
3. `titles.sort_order` orders titles within their category group.

This keeps the policy explicit when a person has titles from several categories, such as `Ustaz Dr. Ahmad Rahman, PhD, Ir.`.

## Morph aliases

The package never hardcodes the `'person'` morph alias. The host application registers its morph map from its ServiceProvider:

```php
persons_register_morph_map('person');
```

This stores short, stable type strings (`person`) instead of FQCNs in assignment and involvement junctions.

## Related Packages

- `aiarmada/commerce-support` — shared primitives
- `aiarmada/addressing` — optional, for country resolution
- `aiarmada/events` — links persons via `involveable` (application-level wiring)
- Future `aiarmada/filament-persons` — Filament admin UI

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
