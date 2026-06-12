---
title: Filament Contacting Package Implementation Instruction
status: implementation-guide
package: aiarmada/filament-contacting
scope: new Filament v5 adapter package for aiarmada/contacting
---

# Filament Contacting Package Implementation Instruction

## 0. Purpose

Create a new Laravel package:

```txt
aiarmada/filament-contacting
```

This package is a **Filament v5 adapter** for the core package:

```txt
aiarmada/contacting
```

The package provides admin UI, reusable Filament form schemas, table schemas, infolists, relation managers, and optional resources for managing contact methods and social profiles.

It must not own the contact/social domain.

Use this mental model:

```txt
contacting           = domain brain
filament-contacting  = admin UI face
```

Do not let the face become the brain. Nanti form field rasa dia architect. 😂

---

## 1. Non-Negotiable Rules

Follow `AGENTS.md` first. If this instruction conflicts with `AGENTS.md`, `AGENTS.md` wins.

Hard requirements:

```txt
- Target PHP 8.4+.
- Use Filament v5 APIs.
- This package is an adapter, not a domain owner.
- Do not create contact/social domain tables in this package.
- Do not add database migrations in v1.
- Do not duplicate ContactMethod, SocialProfile, or ContactSnapshot models.
- Do not duplicate normalization, verification, primary selection, visibility, snapshot, or contact-link logic.
- Do not send email, SMS, WhatsApp, Telegram, or social messages.
- Do not implement sharing buttons or share analytics here.
- Do not implement addressing/location/map logic here.
- Do not add database foreign-key constraints or cascades.
- Do not use SoftDeletes.
- Do not run repo-wide Pint, Pest, PHPStan, or Rector.
- Use package-scoped verification only.
- Use built-in Filament Import and Export actions only.
- Filament tenancy is not a security boundary.
- Every query and action must remain owner-safe if the core Contacting models are owner-scoped.
- Snapshots must be read-only by default.
```

Important boundary:

```txt
If a feature changes contact/social business behavior, implement it in aiarmada/contacting.
If a feature only changes how the user manages or views that behavior in Filament, implement it here.
```

---

## 2. Required Context Reads Before Coding

Every agent must read these before editing:

```txt
CONTEXT-MAP.md
packages/contacting/CONTEXT.md
packages/contacting/docs/01-overview.md
packages/contacting/docs/03-configuration.md
packages/contacting/docs/04-usage.md
packages/filament-contacting/CONTEXT.md, after created
```

If the implementation touches another Filament resource, such as institutions, venues, speakers, customers, or events, also read that package's `CONTEXT.md` and docs before editing.

Do not search blindly. Route through package contexts first.

---

## 3. Package Boundary

### 3.1 This Package Owns

```txt
- Filament plugin registration for contacting UI.
- Filament resources for ContactMethod, SocialProfile, and read-only ContactSnapshot.
- Reusable Filament form schema classes.
- Reusable Filament table schema classes.
- Reusable Filament infolist schema classes.
- Relation managers for contactable/socialable records.
- Optional import/export UI using Filament built-ins.
- UI configuration for navigation, visibility, labels, icons, and enabled resources.
- Filament-specific tests and docs.
```

### 3.2 This Package Does Not Own

```txt
- contact_methods table.
- social_profiles table.
- contact_snapshots table.
- ContactMethod model.
- SocialProfile model.
- ContactSnapshot model.
- HasContactMethods trait.
- HasSocialProfiles trait.
- contactable/socialable relationship definitions.
- contact method normalization.
- phone/email/URL/WhatsApp/social handle normalization.
- contact verification logic.
- primary contact/social selection logic.
- contact snapshot creation logic.
- addressing or map links.
- engagement/sharing actions.
```

When needed, call Actions/Services from `aiarmada/contacting`.

Do **not** reimplement domain rules in Filament components.

---

## 4. Package Identity

Package path:

```txt
packages/filament-contacting
```

Composer package:

```txt
aiarmada/filament-contacting
```

Namespace:

```php
AiArmada\FilamentContacting
```

Main service provider:

```txt
packages/filament-contacting/src/FilamentContactingServiceProvider.php
```

Filament plugin:

```txt
packages/filament-contacting/src/FilamentContactingPlugin.php
```

Config:

```txt
packages/filament-contacting/config/filament-contacting.php
```

Docs:

```txt
packages/filament-contacting/docs/01-overview.md
packages/filament-contacting/docs/02-installation.md
packages/filament-contacting/docs/03-configuration.md
packages/filament-contacting/docs/04-usage.md
packages/filament-contacting/docs/99-troubleshooting.md
```

Context file:

```txt
packages/filament-contacting/CONTEXT.md
```

---

## 5. Required File Tree

Create this structure exactly unless sibling packages already use a very specific package convention that must be followed.

```txt
packages/filament-contacting/
├── CONTEXT.md
├── composer.json
├── config/
│   └── filament-contacting.php
├── docs/
│   ├── 01-overview.md
│   ├── 02-installation.md
│   ├── 03-configuration.md
│   ├── 04-usage.md
│   └── 99-troubleshooting.md
├── src/
│   ├── FilamentContactingServiceProvider.php
│   ├── FilamentContactingPlugin.php
│   ├── Resources/
│   │   ├── ContactMethodResource.php
│   │   ├── ContactMethodResource/
│   │   │   └── Pages/
│   │   │       ├── ListContactMethods.php
│   │   │       ├── CreateContactMethod.php
│   │   │       ├── ViewContactMethod.php
│   │   │       └── EditContactMethod.php
│   │   ├── SocialProfileResource.php
│   │   ├── SocialProfileResource/
│   │   │   └── Pages/
│   │   │       ├── ListSocialProfiles.php
│   │   │       ├── CreateSocialProfile.php
│   │   │       ├── ViewSocialProfile.php
│   │   │       └── EditSocialProfile.php
│   │   ├── ContactSnapshotResource.php
│   │   └── ContactSnapshotResource/
│   │       └── Pages/
│   │           ├── ListContactSnapshots.php
│   │           └── ViewContactSnapshot.php
│   ├── Schemas/
│   │   ├── ContactMethodFormSchema.php
│   │   ├── ContactMethodInfolistSchema.php
│   │   ├── SocialProfileFormSchema.php
│   │   ├── SocialProfileInfolistSchema.php
│   │   └── ContactSnapshotInfolistSchema.php
│   ├── Tables/
│   │   ├── ContactMethodTable.php
│   │   ├── SocialProfileTable.php
│   │   └── ContactSnapshotTable.php
│   ├── RelationManagers/
│   │   ├── ContactMethodsRelationManager.php
│   │   └── SocialProfilesRelationManager.php
│   ├── Imports/
│   │   ├── ContactMethodImporter.php
│   │   └── SocialProfileImporter.php
│   ├── Exports/
│   │   ├── ContactMethodExporter.php
│   │   └── SocialProfileExporter.php
│   └── Support/
│       ├── ContactingFilamentConfig.php
│       ├── GuardsContactingUi.php
│       └── ResolvesContactingModels.php
└── tests/
    ├── Feature/
    │   ├── FilamentContactingPluginTest.php
    │   ├── ContactMethodResourceTest.php
    │   ├── SocialProfileResourceTest.php
    │   ├── ContactSnapshotResourceTest.php
    │   ├── ContactMethodsRelationManagerTest.php
    │   ├── SocialProfilesRelationManagerTest.php
    │   └── ResourceToggleTest.php
    └── TestCase.php
```

Do not add `database/migrations` unless a later task explicitly introduces UI-only package state. Version 1 must be migration-free.

---

## 6. Composer Configuration

Create `packages/filament-contacting/composer.json`.

Minimum structure:

```json
{
  "name": "aiarmada/filament-contacting",
  "description": "Filament v5 adapter for aiarmada/contacting.",
  "type": "library",
  "license": "proprietary",
  "autoload": {
    "psr-4": {
      "AiArmada\\FilamentContacting\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "AiArmada\\FilamentContacting\\Tests\\": "tests/"
    }
  },
  "require": {
    "php": "^8.4",
    "aiarmada/contacting": "*",
    "filament/filament": "^5.0"
  },
  "extra": {
    "laravel": {
      "providers": [
        "AiArmada\\FilamentContacting\\FilamentContactingServiceProvider"
      ]
    }
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

Adjust package version constraints to match monorepo conventions.

Do not add optional unrelated dependencies.

---

## 7. CONTEXT.md

Create `packages/filament-contacting/CONTEXT.md`.

Required content:

```md
---
title: Filament Contacting Context
package: aiarmada/filament-contacting
status: active
surface: filament
family: contacting
---

## Snapshot

Composer package: `aiarmada/filament-contacting`.

Role: Filament v5 adapter for `aiarmada/contacting`. Provides resources, relation managers, form schemas, table schemas, infolists, and optional import/export UI for contact methods, social profiles, and read-only contact snapshots.

Start search paths:

- `packages/filament-contacting/src`
- `packages/filament-contacting/config`
- `packages/filament-contacting/docs`
- `packages/filament-contacting/tests`

Related packages:

- `packages/contacting`
- `packages/commerce-support`, when owner scoping is enabled
- consuming Filament packages that embed contact/social relation managers

## Read next

- `docs/01-overview.md`
- `docs/03-configuration.md`
- `docs/04-usage.md`
- `docs/99-troubleshooting.md`
- `docs/02-installation.md`
- `../contacting/CONTEXT.md`

## Guardrails

This package is an adapter. It does not own contact/social models, migrations, normalization, verification, primary selection, snapshots, or communication sending.

Call core `aiarmada/contacting` Actions/Services for domain behavior.

Filament tenancy is not a security boundary. Resource queries, relation managers, and action handlers must remain owner-safe when core contacting models are owner-scoped.

Snapshots are read-only by default.

When public UI behavior or config changes, update docs in the same pass.
```

---

## 8. Configuration

Create `packages/filament-contacting/config/filament-contacting.php`.

Filament package config section order must be:

```txt
Navigation -> Tables -> Features -> Resources
```

Use minimal keys. Every key must be read somewhere.

Recommended config:

```php
<?php

return [
    'navigation' => [
        'group' => 'Contacting',
        'sort' => 70,
        'icons' => [
            'contact_methods' => 'heroicon-o-phone',
            'social_profiles' => 'heroicon-o-share',
            'contact_snapshots' => 'heroicon-o-archive-box',
        ],
    ],

    'tables' => [
        'default_pagination' => 25,
        'show_owner_columns' => false,
        'show_verification_columns' => true,
        'show_visibility_columns' => true,
    ],

    'features' => [
        'standalone_resources' => false,
        'relation_managers' => true,
        'imports' => false,
        'exports' => true,
        'snapshots' => true,
        'verification_badges' => true,
        'open_url_actions' => true,
    ],

    'resources' => [
        'contact_methods' => [
            'enabled' => false,
            'read_only' => false,
        ],
        'social_profiles' => [
            'enabled' => false,
            'read_only' => false,
        ],
        'contact_snapshots' => [
            'enabled' => false,
            'read_only' => true,
        ],
    ],
];
```

Important defaults:

```txt
standalone_resources = false
contact_methods.enabled = false
social_profiles.enabled = false
contact_snapshots.enabled = false
contact_snapshots.read_only = true
```

Why disabled by default?

Many contact/social records belong to other entities such as institutions, venues, customers, organizers, speakers, or branches. Managing them centrally can accidentally expose cross-owner records if the app is tenant-scoped. Relation managers are safer for normal usage because the parent record scopes the relation.

---

## 9. Service Provider

Create `FilamentContactingServiceProvider`.

Responsibilities:

```txt
- Merge package config.
- Publish config.
- Publish docs if sibling packages do this.
- Register translations only if added.
- Do not load migrations.
- Do not register domain models.
- Do not register core contacting services.
```

Example:

```php
<?php

declare(strict_types=1);

namespace AiArmada\FilamentContacting;

use Illuminate\Support\ServiceProvider;

final class FilamentContactingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-contacting.php',
            'filament-contacting',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/filament-contacting.php' => config_path('filament-contacting.php'),
        ], 'filament-contacting-config');
    }
}
```

If sibling packages use Spatie Package Tools, follow that convention instead.

---

## 10. Filament Plugin

Create `FilamentContactingPlugin`.

Purpose:

```txt
- Allow a panel to opt into contacting UI.
- Register resources based on config toggles.
- Keep registration configurable and safe.
```

Example pattern:

```php
<?php

declare(strict_types=1);

namespace AiArmada\FilamentContacting;

use AiArmada\FilamentContacting\Resources\ContactMethodResource;
use AiArmada\FilamentContacting\Resources\ContactSnapshotResource;
use AiArmada\FilamentContacting\Resources\SocialProfileResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentContactingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'aiarmada-filament-contacting';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if (config('filament-contacting.resources.contact_methods.enabled', false)) {
            $resources[] = ContactMethodResource::class;
        }

        if (config('filament-contacting.resources.social_profiles.enabled', false)) {
            $resources[] = SocialProfileResource::class;
        }

        if (config('filament-contacting.resources.contact_snapshots.enabled', false)) {
            $resources[] = ContactSnapshotResource::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }
    }

    public function boot(Panel $panel): void
    {
        // Keep empty unless the installed Filament v5 version requires panel boot logic.
    }

    public static function make(): self
    {
        return app(self::class);
    }
}
```

Before shipping, verify the exact Filament v5 plugin signatures in the installed version. Do not guess if the local installed version differs.

Usage in an app panel provider:

```php
use AiArmada\FilamentContacting\FilamentContactingPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentContactingPlugin::make(),
        ]);
}
```

---

## 11. Model Resolution

Do not hardcode package internals everywhere.

Create `Support/ResolvesContactingModels.php` if the core package allows model class remapping.

Example:

```php
<?php

declare(strict_types=1);

namespace AiArmada\FilamentContacting\Support;

use AiArmada\Contacting\Models\ContactMethod;
use AiArmada\Contacting\Models\ContactSnapshot;
use AiArmada\Contacting\Models\SocialProfile;

final class ResolvesContactingModels
{
    public function contactMethod(): string
    {
        return config('contacting.models.contact_method', ContactMethod::class);
    }

    public function socialProfile(): string
    {
        return config('contacting.models.social_profile', SocialProfile::class);
    }

    public function contactSnapshot(): string
    {
        return config('contacting.models.contact_snapshot', ContactSnapshot::class);
    }
}
```

If the core package does not support model remapping, directly use the core models.

---

## 12. UI Guard

Create `Support/GuardsContactingUi.php`.

Purpose:

```txt
- Centralize resource enabled/read-only checks.
- Prevent write actions when configured read-only.
- Prevent snapshot mutation.
- Make future owner-scope checks greppable.
```

Example:

```php
<?php

declare(strict_types=1);

namespace AiArmada\FilamentContacting\Support;

final class GuardsContactingUi
{
    public function contactMethodsReadOnly(): bool
    {
        return (bool) config('filament-contacting.resources.contact_methods.read_only', false);
    }

    public function socialProfilesReadOnly(): bool
    {
        return (bool) config('filament-contacting.resources.social_profiles.read_only', false);
    }

    public function contactSnapshotsReadOnly(): bool
    {
        return (bool) config('filament-contacting.resources.contact_snapshots.read_only', true);
    }
}
```

Use this guard in resource pages, table actions, and relation managers.

---

## 13. Contact Method Form Schema

Create `Schemas/ContactMethodFormSchema.php`.

It must be reusable by:

```txt
- ContactMethodResource
- ContactMethodsRelationManager
- consuming package resources that want to embed contact forms
```

Required fields:

```txt
type
label
value
normalized_value, read-only/display only if exposed
country_code
is_primary
is_public
is_verified, usually admin-only
verified_at, usually read-only
metadata, optional advanced JSON field
```

But the form should normally ask only for:

```txt
type
label
value
country_code
is_primary
is_public
```

`normalized_value`, `is_verified`, and `verified_at` are domain/system results and should not be casually edited.

Example schema:

```php
<?php

declare(strict_types=1);

namespace AiArmada\FilamentContacting\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class ContactMethodFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(): array
    {
        return [
            Section::make('Contact Method')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(self::typeOptions())
                            ->required()
                            ->searchable()
                            ->native(false),

                        TextInput::make('label')
                            ->label('Label')
                            ->maxLength(255)
                            ->placeholder('Admin, Office, Support, PIC'),
                    ]),

                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(2048)
                        ->placeholder('hello@example.com, +60123456789, https://example.com'),

                    Grid::make(3)->schema([
                        TextInput::make('country_code')
                            ->label('Country Code')
                            ->maxLength(2)
                            ->placeholder('MY')
                            ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper($state)),

                        Toggle::make('is_primary')
                            ->label('Primary'),

                        Toggle::make('is_public')
                            ->label('Public')
                            ->default(true),
                    ]),
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return config('contacting.contact_methods.types', [
            'email' => 'Email',
            'phone' => 'Phone',
            'mobile' => 'Mobile',
            'whatsapp' => 'WhatsApp',
            'website' => 'Website',
            'telegram' => 'Telegram',
            'fax' => 'Fax',
            'other' => 'Other',
        ]);
    }
}
```

Adjust component namespaces if the installed Filament v5 version differs.

### Required Form Behavior

When the form is submitted:

```txt
- Trim value.
- Uppercase country_code.
- Let the core contacting package normalize normalized_value.
- Do not implement normalization here except simple UI-friendly transforms.
- If is_primary is true, call the core action that enforces one primary per contactable/type.
```

If the core package does not expose a primary-enforcement Action yet, add it to `aiarmada/contacting`, not this adapter.

---

## 14. Social Profile Form Schema

Create `Schemas/SocialProfileFormSchema.php`.

Required logical fields:

```txt
platform
label
handle
url
display_name
is_primary
is_public
is_verified
verified_at
metadata
```

Normal UI fields:

```txt
platform
label
handle
url
display_name
is_primary
is_public
```

Example:

```php
<?php

declare(strict_types=1);

namespace AiArmada\FilamentContacting\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

final class SocialProfileFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function make(): array
    {
        return [
            Section::make('Social Profile')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('platform')
                            ->label('Platform')
                            ->options(self::platformOptions())
                            ->required()
                            ->searchable()
                            ->native(false),

                        TextInput::make('label')
                            ->label('Label')
                            ->maxLength(255)
                            ->placeholder('Main page, Youth wing, Official channel'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('handle')
                            ->label('Handle')
                            ->maxLength(255)
                            ->placeholder('@example'),

                        TextInput::make('display_name')
                            ->label('Display Name')
                            ->maxLength(255),
                    ]),

                    TextInput::make('url')
                        ->label('URL')
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://facebook.com/example'),

                    Grid::make(2)->schema([
                        Toggle::make('is_primary')
                            ->label('Primary'),

                        Toggle::make('is_public')
                            ->label('Public')
                            ->default(true),
                    ]),
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function platformOptions(): array
    {
        return config('contacting.social_profiles.platforms', [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'x' => 'X / Twitter',
            'threads' => 'Threads',
            'telegram' => 'Telegram',
            'telegram_channel' => 'Telegram Channel',
            'telegram_group' => 'Telegram Group',
            'website' => 'Website',
            'other' => 'Other',
        ]);
    }
}
```

Do not confuse social profile storage with share button generation. A Facebook profile is not the same as sharing an event to Facebook.

---

## 15. Tables

Create reusable table builders:

```txt
Tables/ContactMethodTable.php
Tables/SocialProfileTable.php
Tables/ContactSnapshotTable.php
```

### 15.1 ContactMethodTable

Columns:

```txt
type
label
value
normalized_value, optional hidden/toggleable
country_code
is_primary
is_public
is_verified
verified_at
created_at
updated_at
```

Filters:

```txt
type
is_primary
is_public
is_verified
country_code
```

Actions:

```txt
View
Edit, unless read-only
Delete, unless read-only and unless core package disallows delete
Open link/action for website/email/phone/WhatsApp if core package exposes formatted link
```

Bulk actions:

```txt
Delete, only if not read-only
Export, if exports enabled
```

Important:

```txt
Do not send messages from table actions.
Open URL only. No communication side effects.
```

### 15.2 SocialProfileTable

Columns:

```txt
platform
label
handle
url
display_name
is_primary
is_public
is_verified
verified_at
created_at
updated_at
```

Filters:

```txt
platform
is_primary
is_public
is_verified
```

Actions:

```txt
View
Edit, unless read-only
Delete, unless read-only
Open URL, if url exists
```

### 15.3 ContactSnapshotTable

Columns:

```txt
snapshotable_type
snapshotable_id
reason
contact/social payload summary
created_at
```

Actions:

```txt
View only
No edit
No delete by default
```

Snapshots are historical. Treat them like receipts, not draft notes.

---

## 16. Infolists

Create:

```txt
ContactMethodInfolistSchema
SocialProfileInfolistSchema
ContactSnapshotInfolistSchema
```

Infolists must show:

```txt
Contact Method:
- type
- label
- value
- normalized_value
- country_code
- primary/public/verified badges
- verified_at
- created_at/updated_at
- metadata, collapsed if shown

Social Profile:
- platform
- label
- handle
- url
- display_name
- primary/public/verified badges
- verified_at
- created_at/updated_at
- metadata, collapsed if shown

Snapshot:
- snapshotable type/id
- reason
- stored payload
- created_at
```

Do not expose sensitive private contact values by default in public-facing panels. Admin panels may show them depending on authorization.

---

## 17. Resources

Standalone resources are optional and disabled by default.

### 17.1 ContactMethodResource

Model:

```php
AiArmada\Contacting\Models\ContactMethod
```

Resource features:

```txt
- List contact methods.
- Create contact method only if enabled and not read-only.
- View contact method.
- Edit contact method only if enabled and not read-only.
- Delete contact method only if enabled and not read-only.
- Import/export only if feature enabled.
```

Important issue:

A central create page cannot know what `contactable_type/contactable_id` should be unless the UI includes those fields or the current owner context resolves them.

Therefore, one of these must be true before enabling central create:

```txt
1. The resource is used only by global admins and includes safe contactable selector fields.
2. The app provides a scoped owner/contactable context.
3. The resource disables Create and only allows list/view/edit of existing records.
```

Default: central standalone create should be disabled.

### 17.2 SocialProfileResource

Same rules as `ContactMethodResource`.

Do not allow creating an orphan social profile without a socialable entity.

### 17.3 ContactSnapshotResource

Read-only by default.

Pages:

```txt
ListContactSnapshots
ViewContactSnapshot
```

No create page.
No edit page.
No delete action by default.

If a privileged admin later needs purge/delete, that must be a separate explicit task with audit rules.

---

## 18. Resource Query Safety

Every resource must implement owner-safe queries when needed.

Pattern:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    // If core contacting model has owner scopes, do not remove them.
    // Do not call withoutGlobalScopes() unless immediately reapplying owner-safe scoping.

    return $query;
}
```

Do not do this casually:

```php
return parent::getEloquentQuery()->withoutGlobalScopes();
```

If owner scoping is enabled through `commerce-support`, rely on the core model's global scope or call the correct owner-safe query helper from `commerce-support`.

If the standalone resources are enabled and the core package is owner-scoped, add a test proving tenant A cannot see tenant B contact/social records.

---

## 19. Relation Managers

Relation managers are the most important part of this package.

Create:

```txt
RelationManagers/ContactMethodsRelationManager.php
RelationManagers/SocialProfilesRelationManager.php
```

They must work inside resources for models using:

```php
use AiArmada\Contacting\Traits\HasContactMethods;
use AiArmada\Contacting\Traits\HasSocialProfiles;
```

### 19.1 Usage Example in Consuming Package

```php
use AiArmada\FilamentContacting\RelationManagers\ContactMethodsRelationManager;
use AiArmada\FilamentContacting\RelationManagers\SocialProfilesRelationManager;

public static function getRelations(): array
{
    return [
        ContactMethodsRelationManager::class,
        SocialProfilesRelationManager::class,
    ];
}
```

### 19.2 ContactMethodsRelationManager Requirements

Relationship:

```php
protected static string $relationship = 'contactMethods';
```

Must support:

```txt
- list related contact methods
- create related contact method
- edit related contact method
- delete related contact method, if allowed
- set primary contact method safely through core action if needed
- show public/private and verified badges
- open link action when useful
```

Do not let users change `contactable_type` or `contactable_id` manually inside the relation manager.

The parent relation scopes that automatically.

### 19.3 SocialProfilesRelationManager Requirements

Relationship:

```php
protected static string $relationship = 'socialProfiles';
```

Must support:

```txt
- list related social profiles
- create related social profile
- edit related social profile
- delete related social profile, if allowed
- set primary social profile safely through core action if needed
- open profile URL action
```

Do not let users edit `socialable_type` or `socialable_id` manually inside the relation manager.

### 19.4 Primary Toggle Behavior

If form has `is_primary` and user sets it true:

```txt
- Do not only save boolean.
- Call core package action that unsets other primary records for same parent and type/platform.
```

Suggested core actions:

```txt
SetPrimaryContactMethodAction
SetPrimarySocialProfileAction
```

If those actions do not exist, add them to the core `contacting` package first.

Do not implement primary conflict resolution in Filament.

---

## 20. Create/Edit Action Data Mutation

Filament form mutation may be used only to prepare UI input.

Allowed:

```txt
- trim strings
- uppercase country_code
- convert empty string to null
```

Not allowed:

```txt
- domain normalization rules
- phone parsing logic
- WhatsApp link generation
- verification rules
- primary conflict resolution
```

Those belong in `aiarmada/contacting`.

Example relation manager hook:

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['country_code'] = isset($data['country_code'])
        ? strtoupper((string) $data['country_code'])
        : null;

    return $data;
}
```

If the core package exposes `CreateContactMethodAction`, prefer calling that from the create action instead of direct relationship creation.

---

## 21. Import and Export

Use Filament built-in Import and Export actions only.

### 21.1 Defaults

```txt
imports = false
exports = true
```

Why imports disabled by default?

Because imports can create many records and may need careful owner/contactable resolution.

### 21.2 ContactMethodImporter

Only implement if enabled by task/config.

Importer columns:

```txt
contactable_type
contactable_id
type
label
value
country_code
is_primary
is_public
metadata
```

Validation:

```txt
- contactable_type/contactable_id required for standalone imports.
- type required.
- value required.
- country_code max 2, uppercase.
- is_primary boolean.
- is_public boolean.
```

Security:

```txt
Inbound contactable IDs must be owner-safe.
Do not attach records to a model the current owner/user cannot access.
```

If owner-safe resolver is missing, do not implement import yet. Return clear docs saying import requires an application-specific resolver.

### 21.3 SocialProfileImporter

Importer columns:

```txt
socialable_type
socialable_id
platform
label
handle
url
display_name
is_primary
is_public
metadata
```

Same owner-safety rules apply.

### 21.4 Exporters

Exporters may include:

```txt
contactable_type/contactable_id
socialable_type/socialable_id
type/platform
label
value/handle/url
country_code
primary/public/verified flags
created_at
updated_at
```

Do not export private contacts by default unless the admin has permission or config allows it.

---

## 22. Open Link Actions

This package may provide UI actions that open contact/social links.

Examples:

```txt
email    -> mailto:hello@example.com
phone    -> tel:+60123456789
whatsapp -> https://wa.me/60123456789
website  -> https://example.com
social   -> stored url
```

But link building must come from the core package if it exists.

Preferred core action/service:

```txt
BuildContactMethodLinkAction
BuildSocialProfileLinkAction
```

If missing, either:

```txt
- add it to aiarmada/contacting first, or
- show stored URL/value only without generating advanced links.
```

Do not send messages.

An `Open URL` action is okay.
A `Send WhatsApp message` action is not okay in this package.

---

## 23. Verification Badges

If `is_verified` exists, show badge only.

Do not implement verification workflows here unless the core package already exposes actions.

Allowed:

```txt
- display verified/unverified badge
- display verified_at
```

Not allowed:

```txt
- send verification email/SMS
- verify by clicking in Filament unless core action exists
- invent token verification flow
```

If a `MarkContactMethodVerifiedAction` exists in core, Filament may expose it behind config and authorization.

Default: display only.

---

## 24. Authorization

Use package/app policy conventions.

Resources should respect policies on core models when present.

Examples:

```txt
ContactMethodPolicy
SocialProfilePolicy
ContactSnapshotPolicy
```

If no policies exist, follow the existing package/Filament conventions.

Do not bypass authorization in relation managers.

Read-only config must override action availability.

Example:

```php
Tables\Actions\EditAction::make()
    ->visible(fn (): bool => ! app(GuardsContactingUi::class)->contactMethodsReadOnly());
```

Use installed Filament v5 method names after verifying local signatures.

---

## 25. Multitenancy and Owner Safety

This is critical.

Filament UI scoping is not security.

If core `contact_methods` or `social_profiles` use owner scoping from `commerce-support`:

```txt
- Resource list queries must be owner-scoped.
- Relation manager records must be scoped through the parent record and owner-safe parent query.
- Imports must validate contactable/socialable IDs server-side.
- Bulk actions must not operate cross-tenant.
- Counts, badges, filters, and exports must respect owner scope.
```

Do not use `withoutGlobalScopes()` unless immediately reapplying intentional owner scope.

Add cross-tenant tests if owner-scoped behavior is enabled in test fixtures.

---

## 26. Snapshot Resource Rules

Contact snapshots are historical.

Default behavior:

```txt
- List snapshots only if resource enabled.
- View snapshots.
- No create.
- No edit.
- No delete.
- No bulk delete.
- No import.
```

Snapshot UI should display:

```txt
reason
snapshotable type/id
contact data payload
social data payload, if applicable
created_at
```

If snapshot payload includes private contact data, show it only in admin panels with proper authorization.

---

## 27. Documentation Requirements

Create the required package docs.

### 27.1 `docs/01-overview.md`

Must explain:

```txt
- What filament-contacting is.
- It is a Filament v5 adapter for aiarmada/contacting.
- It does not own domain logic.
- Relation managers are the recommended default integration.
- Standalone resources are disabled by default.
- Snapshots are read-only.
```

### 27.2 `docs/02-installation.md`

Must include:

```txt
- composer/monorepo installation notes.
- config publish command.
- plugin registration example in PanelProvider.
- requirement that aiarmada/contacting migrations run first.
```

Example:

```php
use AiArmada\FilamentContacting\FilamentContactingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentContactingPlugin::make(),
        ]);
}
```

### 27.3 `docs/03-configuration.md`

Must explain every config key.

Use this order:

```txt
Navigation
Tables
Features
Resources
```

### 27.4 `docs/04-usage.md`

Must include copy-paste examples for:

```txt
- Adding relation managers to an InstitutionResource.
- Adding relation managers to a SpeakerResource.
- Enabling standalone resources.
- Making snapshots visible/read-only.
- Exporting social profiles.
- Showing open URL actions.
```

### 27.5 `docs/99-troubleshooting.md`

Must include:

```txt
- Resources not visible.
- Plugin not registered.
- Standalone resources disabled by default.
- Relation manager relationship missing.
- Create button hidden due to read-only config.
- Cross-tenant records visible, how to fix.
- Import failing because contactable/socialable resolver missing.
- Filament method signature mismatch.
```

---

## 28. Tests

Add tests in `packages/filament-contacting/tests` only.

Use Pest or PHPUnit according to package conventions.

Every Pest/PHPUnit command must include `--parallel`.

### 28.1 Required Tests

#### Plugin Tests

```txt
- Plugin registers no standalone resources when resources are disabled.
- Plugin registers ContactMethodResource when enabled.
- Plugin registers SocialProfileResource when enabled.
- Plugin registers ContactSnapshotResource when enabled.
```

#### Config Tests

```txt
- Default standalone_resources is false.
- Contact method resource disabled by default.
- Social profile resource disabled by default.
- Contact snapshot resource disabled by default.
- Contact snapshot read_only is true by default.
```

#### Form Schema Tests

```txt
- ContactMethodFormSchema includes type, label, value, country_code, is_primary, is_public.
- SocialProfileFormSchema includes platform, label, handle, url, display_name, is_primary, is_public.
- URL fields validate as URL where applicable.
```

#### Resource Tests

```txt
- ContactMethodResource list page can render when enabled.
- ContactMethodResource create page is unavailable or hidden when disabled/read-only.
- SocialProfileResource list page can render when enabled.
- ContactSnapshotResource has no create/edit pages.
- ContactSnapshotResource view page renders snapshot data.
```

#### Relation Manager Tests

```txt
- ContactMethodsRelationManager creates a contact method for parent record.
- It does not expose contactable_type/contactable_id manual input.
- SocialProfilesRelationManager creates a social profile for parent record.
- It does not expose socialable_type/socialable_id manual input.
- Primary toggle delegates to or results in core primary behavior.
```

#### Owner Safety Tests, If Applicable

```txt
- Tenant A cannot see Tenant B contact methods.
- Tenant A cannot edit Tenant B social profiles.
- Exports are owner-scoped.
- Imports cannot attach to inaccessible contactable/socialable records.
```

#### Read-Only Tests

```txt
- Read-only contact method config hides create/edit/delete actions.
- Read-only social profile config hides create/edit/delete actions.
- Snapshot resource is read-only regardless of generic resource settings.
```

---

## 29. Verification Commands

Run package-scoped checks only.

```bash
./vendor/bin/pest --parallel packages/filament-contacting/tests
```

```bash
./vendor/bin/phpstan analyse packages/filament-contacting/src --level=6
```

Run Pint only on changed package files:

```bash
./vendor/bin/pint packages/filament-contacting/src packages/filament-contacting/config packages/filament-contacting/tests
```

Grep for forbidden domain leakage:

```bash
rg -n -- "Schema::|Blueprint|constrained\(|cascadeOnDelete\(|SoftDeletes" packages/filament-contacting
```

Grep for accidental sending side effects:

```bash
rg -n -- "Mail::|Notification::|Http::|Sms|send\(|post\(|client->" packages/filament-contacting/src
```

Grep for unsafe global scope removal:

```bash
rg -n -- "withoutGlobalScopes\(|withoutGlobalScope\(" packages/filament-contacting/src
```

Grep config reads:

```bash
rg -n -- "config\('filament-contacting\." packages/filament-contacting/src packages/filament-contacting/config
```

If a command cannot be run, state exactly what must be run locally.

---

## 30. Multi-Agent Work Split

The checklist below is designed so multiple agents can work without overlapping.

### Agent A — Package Skeleton and Docs

Owns:

```txt
packages/filament-contacting/composer.json
packages/filament-contacting/CONTEXT.md
packages/filament-contacting/config/filament-contacting.php
packages/filament-contacting/src/FilamentContactingServiceProvider.php
packages/filament-contacting/src/FilamentContactingPlugin.php
packages/filament-contacting/docs/*
```

Tasks:

```txt
- Create package skeleton.
- Add service provider.
- Add Filament plugin.
- Add config with correct section order.
- Add required docs.
- Do not create resources yet unless coordinating with Agent C.
```

### Agent B — Schemas, Tables, Infolists

Owns:

```txt
packages/filament-contacting/src/Schemas/*
packages/filament-contacting/src/Tables/*
```

Tasks:

```txt
- Create ContactMethodFormSchema.
- Create SocialProfileFormSchema.
- Create ContactMethodInfolistSchema.
- Create SocialProfileInfolistSchema.
- Create ContactSnapshotInfolistSchema.
- Create reusable table builders.
- Do not create Resources or RelationManagers.
```

### Agent C — Resources and Pages

Owns:

```txt
packages/filament-contacting/src/Resources/*
```

Tasks:

```txt
- Create ContactMethodResource and pages.
- Create SocialProfileResource and pages.
- Create ContactSnapshotResource and read-only pages.
- Use schemas/tables from Agent B.
- Respect config toggles.
- Do not implement domain logic.
```

### Agent D — Relation Managers

Owns:

```txt
packages/filament-contacting/src/RelationManagers/*
```

Tasks:

```txt
- Create ContactMethodsRelationManager.
- Create SocialProfilesRelationManager.
- Use schemas/tables from Agent B.
- Prevent manual editing of morph keys.
- Ensure create/edit uses core package behavior where applicable.
```

### Agent E — Import/Export

Owns:

```txt
packages/filament-contacting/src/Imports/*
packages/filament-contacting/src/Exports/*
```

Tasks:

```txt
- Add exporters first.
- Add importers only if feature config and owner-safe resolver are clear.
- Use built-in Filament import/export actions only.
- Do not build custom CSV parser.
- Do not bypass owner safety.
```

### Agent F — Tests and QC

Owns:

```txt
packages/filament-contacting/tests/*
```

Tasks:

```txt
- Add tests for plugin/config/resources/forms/relation managers/read-only snapshots.
- Add owner-safety tests if owner scoping is active.
- Run package-scoped Pest with --parallel.
- Run PHPStan level 6 on package src.
- Run Pint only on changed package files.
- Run grep checks for forbidden patterns.
```

Agents must not edit another agent's owned files without coordination.

---

## 31. Consumer Usage Examples

### 31.1 Add Contacts and Socials to InstitutionResource

```php
<?php

declare(strict_types=1);

namespace AiArmada\Institutions\Filament\Resources;

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

The `Institution` model must use the core package traits:

```php
use AiArmada\Contacting\Traits\HasContactMethods;
use AiArmada\Contacting\Traits\HasSocialProfiles;

final class Institution extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}
```

### 31.2 Add Contacts to SpeakerResource

```php
public static function getRelations(): array
{
    return [
        ContactMethodsRelationManager::class,
        SocialProfilesRelationManager::class,
    ];
}
```

### 31.3 Enable Standalone Resources

In `config/filament-contacting.php`:

```php
'features' => [
    'standalone_resources' => true,
    // ...
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

Then register the plugin in the panel provider.

---

## 32. Common Mistakes to Avoid

```txt
- Creating contact_methods or social_profiles migrations in filament-contacting.
- Duplicating ContactMethod or SocialProfile models.
- Putting phone/email/social normalization in Filament forms.
- Allowing snapshot edit/delete actions.
- Enabling standalone resources by default.
- Letting users manually edit contactable_type/contactable_id inside relation managers.
- Ignoring owner scoping because “Filament already scopes the UI”.
- Using custom import/export logic instead of Filament built-ins.
- Adding notification/sending behavior.
- Adding sharing behavior.
- Adding address/map behavior.
- Running repo-wide Pint and creating noisy diffs.
```

---

## 33. Acceptance Criteria

The implementation is complete when:

```txt
- Package exists at packages/filament-contacting.
- composer.json is valid.
- CONTEXT.md exists and follows required structure.
- Required docs exist with YAML frontmatter title.
- Config exists and follows Filament package section order.
- Service provider merges/publishes config.
- Filament plugin registers resources based on config toggles.
- Standalone resources are disabled by default.
- ContactMethodResource works when enabled.
- SocialProfileResource works when enabled.
- ContactSnapshotResource is read-only when enabled.
- ContactMethodsRelationManager works for contactable models.
- SocialProfilesRelationManager works for socialable models.
- Form schemas are reusable by resources and relation managers.
- Tables/infolists are reusable.
- No migrations are added.
- No domain logic is duplicated.
- No sending/sharing/addressing behavior is added.
- Owner safety is respected.
- Package tests pass with --parallel.
- PHPStan level 6 passes for package src.
- Pint is run only on changed package files.
- Grep checks find no forbidden DB/domain-leak patterns.
```

---

## 34. Final Self-Review Checklist Before Returning Work

Before declaring done, the implementing agent must verify:

```txt
[ ] Did I create no migrations in filament-contacting?
[ ] Did I avoid duplicating core Contacting models?
[ ] Did I avoid normalization/business logic in Filament?
[ ] Did I keep snapshots read-only?
[ ] Did I keep standalone resources disabled by default?
[ ] Did I add relation managers for normal usage?
[ ] Did I prevent manual morph-key editing in relation managers?
[ ] Did I include docs and CONTEXT.md?
[ ] Did I use config keys that are actually read?
[ ] Did I avoid repo-wide formatting/testing?
[ ] Did I run or list the package-scoped verification commands?
```

Final rule:

```txt
Contacting owns the meaning.
Filament-contacting owns the management screen.
```

Anything else is UI wearing a fake moustache pretending to be domain logic. 😂
