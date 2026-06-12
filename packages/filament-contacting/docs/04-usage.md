---
title: Filament Contacting Usage
---

# Usage

## Add Relation Managers to a Resource

In your resource (e.g., `InstitutionResource`), add the relation managers:

```php
use AiArmada\FilamentContacting\RelationManagers\ContactMethodsRelationManager;
use AiArmada\FilamentContacting\RelationManagers\SocialProfilesRelationManager;
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
use AiArmada\Contacting\Concerns\HasContactMethods;
use AiArmada\Contacting\Concerns\HasSocialProfiles;

final class Institution extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}
```

## Add Relation Managers to SpeakerResource

Same pattern as above — the `Speaker` model must use `HasContactMethods` and/or `HasSocialProfiles`.

## Enable Standalone Resources

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

Ensure `open_url_actions` is enabled in config (enabled by default). View and edit forms will show clickable URL links.