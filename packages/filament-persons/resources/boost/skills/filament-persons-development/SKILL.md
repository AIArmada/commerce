---
name: filament-persons-development
description: Develop and integrate Filament Persons with Laravel Dune ownership, security and canonical APIs.
---

# Filament Persons development

- Register `AIArmada\FilamentPersons\FilamentPersonsPlugin` on the panel.
- Use core `CreatePersonAction`, `UpdatePersonAction`, `SavePersonName` and feature-specific title, credential and affiliation actions from resource pages and relation managers.
- Person identities are global, but related institution and country references still require server-side validation. Never authorize an attaching record merely because the related person is global.
- Configure affiliation institution display and search with `filament-persons.resources.institution.label_column` and `search_columns` (plain column names; search columns default to the label column).
- Use resource policies and explicit action authorization. Keep persistence and ordering in the owning persons feature.
