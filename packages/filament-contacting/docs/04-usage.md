---
title: Filament Contacting Usage
---

# Usage

## Add Relation Managers to a Resource

In your resource (e.g., `InstitutionResource`), add the relation managers:

```php
use AIArmada\FilamentContacting\RelationManagers\ContactMethodsRelationManager;
use AIArmada\FilamentContacting\RelationManagers\SocialProfilesRelationManager;
use Filament\Resources\Resource;

final class InstitutionResource extends Resource
{
    public static function getRelations(): array
    {
        return [
            ContactMethodsRelationManager::class,
            SocialProfilesRelationManager::class,
        ];
    }
}
```

The model must use the core traits:

```php
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;

final class Institution extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}
```

## Add Relation Managers to SpeakerResource

Same pattern as above — the `Speaker` model must use `HasContactMethods` and/or `HasSocialProfiles`.

## Host Owner Scoping (Required)

Relation managers list and create children of the host resource's record. The host
resource **must** resolve its record through `OwnerUiScope` (or an equivalent
owner-safe query); otherwise a cross-owner parent would expose its children.
When the parent model is owner-scoped, the relation managers additionally scope
the child table to the parent's owner and refuse creates against an inaccessible
parent. Parents without owner columns cannot be judged — the host query is the
security boundary in that case.

## Enable Standalone Resources

Standalone resources are view-and-edit only: creates are intentionally unavailable
because a standalone form cannot safely pick a parent entity. Create contact
methods and social profiles through the relation managers above so every record
gets its parent.

In `config/filament-contacting.php`:

```php
'features' => [
    'standalone_resources' => true,
],
'resources' => [
    'contact_methods' => [
        'enabled' => true,
        'read_only' => false,
    ],
    'social_profiles' => [
        'enabled' => true,
        'read_only' => false,
    ],
    'contact_snapshots' => [
        'enabled' => true,
        'read_only' => true,
    ],
],
```

Then register the plugin in your Panel provider.

## Make Snapshots Visible (Read-Only)

```php
'resources' => [
    'contact_snapshots' => [
        'enabled' => true,
        'read_only' => true,
    ],
],
```

## Exporting Social Profiles

Ensure `exports` is enabled in config (enabled by default). Export actions appear in the table bulk actions menu.

## Show Open URL Actions

Ensure `open_url_actions` is enabled in config (enabled by default). Social profile
URL table columns and infolist entries render as clickable links opened in a new
tab. Only `http`/`https` URLs are linked; any other stored value renders as plain
text.

## Importing Records

Set `features.imports` to `true` to show an import action on the contact method
and social profile list pages (imports stay hidden while the resource is
read-only). Every CSV parent reference is resolved through the core contacting
owner guard before validation, and rows with an invalid reference fail with a
descriptive reason instead of aborting the import.

Imports are insert-only: each row creates a new record. Re-running an import
re-inserts rows instead of updating them — failing on the core unique backstops
where they apply (for example customer emails) — because match-then-update is
intentionally not supported.

An empty `is_public` cell leaves the flag unset so the core model default applies
(email, phone, mobile, WhatsApp, and fax default to private). Set the cell
explicitly to `true` or `false` to override.