# Persons development

- `AIArmada\Persons\Models\Person` is a shared global identity, not a tenant/customer profile. Resolve configurable host model bindings through `Support\ModelResolver`; do not hardcode host namespaces or morph aliases.
- Use `Features\Identity\Actions\CreatePersonAction`, `UpdatePersonAction`, `SavePersonName` and `ReorderFeaturedPersonsAction` for identity changes. The person-name action maintains searchable identity and prevents reparenting.
- Delete titles, categories and issuers through `Features\Titles\Actions\DeleteTitle`, `DeleteTitleCategory` and `DeleteTitleIssuer` so dependent assignments are cleaned or nulled atomically.
- Use `Features\Titles\Actions\AssignTitleAction` and `ReorderTitleAction`, `Features\Credentials\Actions\AssignCredentialAction`, and `Features\Affiliations\Actions\SaveAffiliation` for their respective capabilities. Models for these capabilities are in their owning feature.
- Validate institution and country references through `Support\PersonsModelReferenceGuard`. Shared person identity does not bypass ownership checks for referenced or attaching records.

- Person DTOs in `AIArmada\Persons\Data` are immutable and framework-independent. Use feature-owned `PersonDataMapper`, `TitleDataMapper`, `CredentialDataMapper` and `AffiliationDataMapper` queries to map persisted models into DTOs; serialize with `toArray()` or JSON.
