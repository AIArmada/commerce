---
title: Persons Overview
---

## Introduction

`aiarmada/persons` is a reusable person identity package for Laravel. It provides a normalized, shared identity layer — a canonical `persons` table plus relational systems for multi-context names, titles, credentials, and affiliations. Assignment tables are polymorphic so any model can participate as a titleable, credentialable, or affiliatable.

`Person` is the shared human identity root and is intentionally unscoped. Tenant-owned commercial data belongs to `customers.Customer`, which may carry a nullable `person_id` link; the link is written explicitly by the customers package and never causes an automatic backfill or merge.

The `person_names`, title taxonomy, credential taxonomy, and assignment rows
inherit this global/shared topology. Do not attach them to an owner-scoped
record and assume the persons tables provide tenant isolation; an application
must authorize that attachment at its owning boundary. Scoping `Person` in a
future release requires scoping the dependent persons tables in the same
release.

## What this package owns

- The canonical `persons` identity table
- Multi-context, multi-language `person_names`
- Titles, title categories, and title issuers
- Polymorphic title assignments (`titleable`)
- Credential definitions and polymorphic credential assignments (`credentialable`)
- Affiliations and the roles held within them
- Formatted display-name composition from ordered title assignments

## Identity topology

| Model | Meaning | Scope |
|---|---|---|
| `Person` | Shared human identity, names, titles, credentials, and affiliations | Global/shared |
| `Customer` | Commercial profile for a person in a tenant | Owner-scoped; nullable `person_id` link |
| `Organization` | Tenant/owner aggregate | Organization-owned |
| `EventOrganizer` | Organizer role/profile in the events domain | Event-scoped |

An event organizer is not an organization and is not a second canonical person
table. Event involvement remains owned by `events`; a host may attach a
`Person` to an event involvement through the events polymorphic contract.

## Generated identity values

When a person is saved, a blank slug is generated from the normalized name and
the person's short UUID, with a numeric collision suffix when necessary.
`searchable_name` is regenerated from the person's name fields and primary
name variants. `status` is cast to `PersonStatus` (`active`, `published`, or
`archived`), and `transitionStatus()` is the single lifecycle entry point for
maintaining `published_at`.

`PersonName` primary status is scoped by `(person_id, name_type,
language_code)`. The model transaction locks the parent person before
demoting siblings. A database partial unique index and the
`(person_id, is_primary)` covering index remain a separately gated index
migration because the current migration track permits only the customers
link migration.

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
| **Person Name** | Multi-context name variant (legal, display, religious, nickname, etc.) with a language code. |
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
- `aiarmada/customers` — owner-scoped commercial profiles with an explicit `person_id` link
- Future `aiarmada/filament-persons` — Filament admin UI

## Requirements

- PHP 8.4+
- Laravel 11+
- `aiarmada/commerce-support`
