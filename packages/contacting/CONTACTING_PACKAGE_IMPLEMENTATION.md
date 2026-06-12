---
title: Contacting Package Implementation Instruction
status: implementation-guide
package: aiarmada/contacting
scope: new core package for contact methods and social profiles
---

# Contacting Package Implementation Instruction

## 0. Purpose

Create a new Laravel package:

```txt
aiarmada/contacting
```

The package provides a reusable domain for attaching **contact methods** and **social profiles** to any entity.

This package exists because contact and social presence are not limited to people. The same concepts apply to:

```txt
people
organizations
NGOs
ministries
shops
businesses
groups
institutions
masjids
surau
venues
branches
speakers
customers
organizers
```

The package must allow any model to have:

```txt
contact methods:
- email
- phone
- mobile
- WhatsApp
- website
- fax
- Telegram
- other communication channels

social profiles:
- Facebook
- Instagram
- TikTok
- YouTube
- LinkedIn
- X/Twitter
- Threads
- Telegram channel/group
- other social/public profiles
```

This package is **not** the addressing package.

Use this mental model:

```txt
addressing = where the entity is physically/geographically
contacting = how to reach or follow the entity
engagement/sharing = how content/entity is shared by users
```

Do not mix these domains.

---

## 1. Hard Boundaries

### 1.1 This package owns

```txt
contact_methods table
social_profiles table
contact_snapshots table, if implemented
contactable polymorphic relationship
socialable polymorphic relationship
contact method normalization
social profile normalization
contact/social formatting
primary contact/social selection
public/private visibility
verification flags
basic generated contact links such as mailto:, tel:, WhatsApp wa.me
```

### 1.2 This package does not own

```txt
physical addresses
countries/areas/postcodes
Google Maps or Waze location links
notifications delivery
sending email/SMS/WhatsApp messages
campaigns
marketing automation
sharing buttons
share tracking
likes/bookmarks/comments/reactions
CRM pipelines
user accounts/authentication
Filament UI
```

If a feature is about “where is the entity?”, it belongs to `aiarmada/addressing`.

If a feature is about “share this event/page/content to WhatsApp/Facebook/etc.”, it belongs to `aiarmada/engagement` or `aiarmada/sharing`, not this package.

Contacting stores the channels. Sharing performs the action.

---

## 2. AGENTS.md Compliance Requirements

Before implementation, every agent must read:

```txt
CONTEXT-MAP.md
packages/contacting/CONTEXT.md
packages/contacting/docs/01-overview.md
packages/contacting/docs/03-configuration.md
```

Implementers must follow these monorepo rules:

```txt
- PHP 8.4+ target.
- Laravel package style consistent with sibling packages.
- UUID primary keys.
- No database foreign-key constraints.
- No database cascades.
- No ->constrained().
- No ->cascadeOnDelete().
- No SoftDeletes.
- Models use Illuminate\Database\Eloquent\Concerns\HasUuids.
- Models must not set protected $table directly.
- Models must implement getTable() using package config.
- JSON columns must use config('contacting.database.json_column_type').
- Business orchestration belongs in Actions.
- Keep controllers/models thin.
- Package docs under packages/contacting/docs are canonical.
- Run package-scoped tests only.
- Pest/PHPUnit commands must include --parallel.
- Do not run repo-wide Pint/PHPStan/Pest unless explicitly requested.
- Filament packages are adapters, not domain owners.
```

---

## 3. Package Name and Namespace

Composer package:

```txt
aiarmada/contacting
```

PHP namespace:

```php
AiArmada\Contacting
```

Package path:

```txt
packages/contacting
```

Main service provider:

```txt
packages/contacting/src/ContactingServiceProvider.php
```

Config file:

```txt
packages/contacting/config/contacting.php
```

Docs:

```txt
packages/contacting/docs/01-overview.md
packages/contacting/docs/02-installation.md
packages/contacting/docs/03-configuration.md
packages/contacting/docs/04-usage.md
packages/contacting/docs/99-troubleshooting.md
```

Package context:

```txt
packages/contacting/CONTEXT.md
```

---

## 4. Required Package Structure

Create this structure:

```txt
packages/contacting/
├── composer.json
├── CONTEXT.md
├── config/
│   └── contacting.php
├── database/
│   ├── factories/
│   │   ├── ContactMethodFactory.php
│   │   ├── SocialProfileFactory.php
│   │   └── ContactSnapshotFactory.php
│   └── migrations/
│       ├── create_contact_methods_table.php
│       ├── create_social_profiles_table.php
│       └── create_contact_snapshots_table.php
├── docs/
│   ├── 01-overview.md
│   ├── 02-installation.md
│   ├── 03-configuration.md
│   ├── 04-usage.md
│   └── 99-troubleshooting.md
├── src/
│   ├── Actions/
│   │   ├── BuildContactLinksAction.php
│   │   ├── CreateContactMethodAction.php
│   │   ├── CreateContactSnapshotAction.php
│   │   ├── CreateSocialProfileAction.php
│   │   ├── NormalizeContactMethodAction.php
│   │   ├── NormalizeSocialProfileAction.php
│   │   ├── SetPrimaryContactMethodAction.php
│   │   ├── SetPrimarySocialProfileAction.php
│   │   ├── UpdateContactMethodAction.php
│   │   └── UpdateSocialProfileAction.php
│   ├── Concerns/
│   │   ├── HasContactMethods.php
│   │   └── HasSocialProfiles.php
│   ├── Contracts/
│   │   ├── ContactMethodNormalizer.php
│   │   └── SocialProfileNormalizer.php
│   ├── Data/
│   │   ├── ContactLinksData.php
│   │   ├── ContactMethodData.php
│   │   ├── ContactSnapshotData.php
│   │   └── SocialProfileData.php
│   ├── Enums/
│   │   ├── ContactMethodType.php
│   │   ├── ContactPurpose.php
│   │   └── SocialPlatform.php
│   ├── Models/
│   │   ├── ContactMethod.php
│   │   ├── ContactSnapshot.php
│   │   └── SocialProfile.php
│   ├── Support/
│   │   ├── NormalizesEmailAddress.php
│   │   ├── NormalizesPhoneNumber.php
│   │   ├── NormalizesUrl.php
│   │   └── NormalizesSocialHandle.php
│   └── ContactingServiceProvider.php
└── tests/
    ├── Feature/
    └── Unit/
```

If sibling packages use a different test namespace/layout, match the sibling style while preserving the responsibilities above.

---

## 5. Composer Requirements

### 5.1 Required dependencies

Use normal Laravel package requirements consistent with the monorepo.

Do not add new external dependencies unless explicitly approved.

Do not add phone-number libraries, social parsers, or URL preview libraries in v1.

### 5.2 Optional integrations

If the repo uses `aiarmada/commerce-support` for owner scoping, integrate with it according to the monorepo owner-scoping contract.

Preferred direction:

```txt
- contact_methods and social_profiles should include owner_type/owner_id columns.
- owner enforcement should be enabled through config when commerce-support is installed and configured.
- direct queries must be owner-safe when owner mode is enabled.
```

If the package cannot safely support owner scoping without hard requiring `commerce-support`, then add `aiarmada/commerce-support` as a required dependency. Do not invent package-local tenancy primitives.

---

## 6. Config File

Create:

```txt
packages/contacting/config/contacting.php
```

Required config:

```php
<?php

declare(strict_types=1);

return [
    'tables' => [
        'contact_methods' => 'contact_methods',
        'social_profiles' => 'social_profiles',
        'contact_snapshots' => 'contact_snapshots',
    ],

    'database' => [
        'json_column_type' => 'json',
    ],

    'defaults' => [
        'country_code' => env('CONTACTING_DEFAULT_COUNTRY_CODE', 'MY'),
        'public_by_default' => true,
        'verified_by_default' => false,
    ],

    'features' => [
        'contact_snapshots' => true,
        'owner_scoping' => env('CONTACTING_OWNER_SCOPING', false),
        'strict_social_platforms' => false,
        'strict_contact_types' => false,
    ],

    'contact_methods' => [
        'types' => [
            'email',
            'phone',
            'mobile',
            'whatsapp',
            'website',
            'telegram',
            'fax',
            'other',
        ],

        'purposes' => [
            'general',
            'admin',
            'support',
            'billing',
            'sales',
            'registration',
            'media',
            'donation',
            'partnership',
            'emergency',
            'privacy',
            'other',
        ],
    ],

    'social_profiles' => [
        'platforms' => [
            'facebook',
            'instagram',
            'tiktok',
            'youtube',
            'linkedin',
            'x',
            'threads',
            'telegram',
            'telegram_channel',
            'telegram_group',
            'whatsapp_channel',
            'website',
            'other',
        ],
    ],
];
```

### 6.1 Config discipline

Do not add config keys unless code reads them.

Every config key added must be used or removed.

JSON columns must use `config('contacting.database.json_column_type')`.

---

## 7. Database Design

### 7.1 Table: `contact_methods`

Create migration:

```txt
packages/contacting/database/migrations/create_contact_methods_table.php
```

Use config table name.

Sample migration:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('contacting.tables.contact_methods', 'contact_methods');
        $jsonColumnType = config('contacting.database.json_column_type', 'json');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();

            $table->nullableMorphs('owner');
            $table->nullableMorphs('contactable');

            $table->string('type');
            $table->string('purpose')->default('general');
            $table->string('label')->nullable();

            $table->text('value');
            $table->text('normalized_value')->nullable();
            $table->text('display_value')->nullable();

            $table->string('country_code', 2)->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['contactable_type', 'contactable_id']);
            $table->index(['owner_type', 'owner_id']);
            $table->index(['type', 'purpose']);
            $table->index(['is_primary']);
            $table->index(['is_public']);
            $table->index(['is_verified']);
            $table->index(['country_code']);
        });
    }
};
```

Important:

```txt
- Do not use ->constrained().
- Do not use ->cascadeOnDelete().
- Do not add DB FK constraints.
- Do not add unique DB constraints for primary contact.
```

Primary-contact uniqueness must be enforced by Actions in application logic.

### 7.2 Table: `social_profiles`

Create migration:

```txt
packages/contacting/database/migrations/create_social_profiles_table.php
```

Sample migration:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('contacting.tables.social_profiles', 'social_profiles');
        $jsonColumnType = config('contacting.database.json_column_type', 'json');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();

            $table->nullableMorphs('owner');
            $table->nullableMorphs('socialable');

            $table->string('platform');
            $table->string('purpose')->default('general');
            $table->string('label')->nullable();

            $table->string('handle')->nullable();
            $table->text('url')->nullable();
            $table->text('normalized_url')->nullable();
            $table->string('display_name')->nullable();
            $table->string('external_id')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_verified')->default(false);

            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['socialable_type', 'socialable_id']);
            $table->index(['owner_type', 'owner_id']);
            $table->index(['platform', 'purpose']);
            $table->index(['is_primary']);
            $table->index(['is_public']);
            $table->index(['is_verified']);
        });
    }
};
```

### 7.3 Table: `contact_snapshots`

Snapshots preserve published/historical contact and social data.

Use cases:

```txt
- event public page should preserve organizer contact shown at publish time
- order/invoice should preserve vendor/customer contact at transaction time
- certificate should preserve institution contact at issue time
```

Create migration:

```txt
packages/contacting/database/migrations/create_contact_snapshots_table.php
```

Sample migration:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('contacting.tables.contact_snapshots', 'contact_snapshots');
        $jsonColumnType = config('contacting.database.json_column_type', 'json');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();

            $table->nullableMorphs('owner');
            $table->nullableMorphs('snapshotable');

            $table->string('snapshot_type');
            // contact_method, social_profile, contact_bundle

            $table->uuid('source_id')->nullable();
            $table->string('source_type')->nullable();
            // contact_method, social_profile, manual

            $table->string('reason')->nullable();
            // event_public_contact, order_billing_contact, organizer_contact, etc.

            $table->string('label')->nullable();
            $table->string('channel')->nullable();
            // email, phone, whatsapp, facebook, instagram, etc.

            $table->text('value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->text('url')->nullable();
            $table->text('display_value')->nullable();

            $table->boolean('is_public')->default(true);

            $table->{$jsonColumnType}('payload')->nullable();
            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['snapshotable_type', 'snapshotable_id']);
            $table->index(['owner_type', 'owner_id']);
            $table->index(['snapshot_type']);
            $table->index(['channel']);
            $table->index(['reason']);
            $table->index(['is_public']);
        });
    }
};
```

If v1 scope is intentionally smaller, the snapshot table may be created but consumers can ignore it. Do not put snapshot behavior in consumer packages if it is generic and reusable.

---

## 8. Model Requirements

### 8.1 General model rules

All package models must:

```txt
- use HasUuids
- implement getTable() with config
- not use protected $table
- not use SoftDeletes
- use typed casts
- have PHPDoc generics for relationships
- implement application-level cleanup in booted() where required
```

### 8.2 ContactMethod model

Path:

```txt
packages/contacting/src/Models/ContactMethod.php
```

Required behavior:

```txt
- morphTo contactable
- morphTo owner when owner columns are used
- casts booleans and timestamps
- metadata cast to array
- scopes for type, purpose, public, primary, verified
- helper methods for isEmail(), isPhone(), isWhatsapp(), etc. only if useful
```

Sample skeleton:

```php
<?php

declare(strict_types=1);

namespace AiArmada\Contacting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class ContactMethod extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('contacting.tables.contact_methods', 'contact_methods');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Adjust owner behavior to the installed `commerce-support` pattern if required.

### 8.3 SocialProfile model

Path:

```txt
packages/contacting/src/Models/SocialProfile.php
```

Required behavior:

```txt
- morphTo socialable
- morphTo owner when owner columns are used
- casts booleans and timestamps
- metadata cast to array
- scopes for platform, purpose, public, primary, verified
```

### 8.4 ContactSnapshot model

Path:

```txt
packages/contacting/src/Models/ContactSnapshot.php
```

Required behavior:

```txt
- morphTo snapshotable
- optional morphTo owner
- payload cast to array
- metadata cast to array
- snapshots are not automatically updated when source changes
```

Do not add update syncing from source to snapshot. Snapshot means historical copy.

---

## 9. Traits / Concerns

### 9.1 HasContactMethods

Path:

```txt
packages/contacting/src/Concerns/HasContactMethods.php
```

Methods required:

```php
contactMethods()
publicContactMethods()
primaryContactMethod(?string $type = null, ?string $purpose = null)
contactMethodsOfType(string $type)
contactMethodsForPurpose(string $purpose)
addContactMethod(ContactMethodData|array $data)
```

Sample:

```php
<?php

declare(strict_types=1);

namespace AiArmada\Contacting\Concerns;

use AiArmada\Contacting\Models\ContactMethod;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContactMethods
{
    /**
     * @return MorphMany<ContactMethod, $this>
     */
    public function contactMethods(): MorphMany
    {
        return $this->morphMany(ContactMethod::class, 'contactable');
    }

    public function primaryContactMethod(?string $type = null, ?string $purpose = null): ?ContactMethod
    {
        return $this->contactMethods()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->first();
    }
}
```

Do not put complex creation/update logic in the trait. Use Actions.

### 9.2 HasSocialProfiles

Path:

```txt
packages/contacting/src/Concerns/HasSocialProfiles.php
```

Methods required:

```php
socialProfiles()
publicSocialProfiles()
primarySocialProfile(?string $platform = null, ?string $purpose = null)
socialProfilesForPlatform(string $platform)
addSocialProfile(SocialProfileData|array $data)
```

---

## 10. Data Objects

Prefer `spatie/laravel-data` if the monorepo already uses it for DTOs. If not, use readonly PHP classes matching sibling package style.

### 10.1 ContactMethodData

Path:

```txt
packages/contacting/src/Data/ContactMethodData.php
```

Required logical fields:

```php
public readonly ?string $id = null;
public readonly string $type;
public readonly string $purpose = 'general';
public readonly ?string $label = null;
public readonly string $value;
public readonly ?string $normalizedValue = null;
public readonly ?string $displayValue = null;
public readonly ?string $countryCode = null;
public readonly bool $isPrimary = false;
public readonly bool $isPublic = true;
public readonly bool $isVerified = false;
public readonly mixed $verifiedAt = null;
public readonly array $metadata = [];
```

Accepted aliases:

```txt
country_code      -> countryCode
countryCode       -> countryCode
normalized_value  -> normalizedValue
normalizedValue   -> normalizedValue
display_value     -> displayValue
displayValue      -> displayValue
is_primary        -> isPrimary
isPrimary         -> isPrimary
is_public         -> isPublic
isPublic          -> isPublic
is_verified       -> isVerified
isVerified        -> isVerified
verified_at       -> verifiedAt
verifiedAt        -> verifiedAt
phone             -> value when type=phone/mobile/whatsapp is inferred
email             -> value when type=email is inferred
website           -> value when type=website is inferred
url               -> value when type=website is inferred
```

The DTO may infer type only in factory helpers such as:

```php
ContactMethodData::email('admin@example.com')
ContactMethodData::phone('+60123456789')
ContactMethodData::whatsapp('+60123456789')
ContactMethodData::website('https://example.com')
```

Do not make inference too magical in the base constructor.

### 10.2 SocialProfileData

Path:

```txt
packages/contacting/src/Data/SocialProfileData.php
```

Required logical fields:

```php
public readonly ?string $id = null;
public readonly string $platform;
public readonly string $purpose = 'general';
public readonly ?string $label = null;
public readonly ?string $handle = null;
public readonly ?string $url = null;
public readonly ?string $normalizedUrl = null;
public readonly ?string $displayName = null;
public readonly ?string $externalId = null;
public readonly bool $isPrimary = false;
public readonly bool $isPublic = true;
public readonly bool $isVerified = false;
public readonly mixed $verifiedAt = null;
public readonly array $metadata = [];
```

Accepted aliases:

```txt
platform          -> platform
network           -> platform
channel           -> platform
handle            -> handle
username          -> handle
url               -> url
profile_url       -> url
profileUrl        -> url
normalized_url    -> normalizedUrl
normalizedUrl     -> normalizedUrl
display_name      -> displayName
displayName       -> displayName
external_id       -> externalId
externalId        -> externalId
is_primary        -> isPrimary
is_public         -> isPublic
is_verified       -> isVerified
verified_at       -> verifiedAt
```

### 10.3 ContactLinksData

Used by `BuildContactLinksAction`.

Required logical fields:

```php
public readonly ?string $mailtoUrl = null;
public readonly ?string $telUrl = null;
public readonly ?string $whatsappUrl = null;
public readonly ?string $websiteUrl = null;
public readonly array $links = [];
```

### 10.4 ContactSnapshotData

Used by `CreateContactSnapshotAction`.

Required logical fields:

```php
public readonly string $snapshotType;
public readonly ?string $sourceType = null;
public readonly ?string $sourceId = null;
public readonly ?string $reason = null;
public readonly ?string $label = null;
public readonly ?string $channel = null;
public readonly ?string $value = null;
public readonly ?string $normalizedValue = null;
public readonly ?string $url = null;
public readonly ?string $displayValue = null;
public readonly bool $isPublic = true;
public readonly array $payload = [];
public readonly array $metadata = [];
```

---

## 11. Enums

Create enums only if sibling packages use enums for fixed values. If not, use string constants.

### 11.1 ContactMethodType

```php
namespace AiArmada\Contacting\Enums;

enum ContactMethodType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Mobile = 'mobile';
    case Whatsapp = 'whatsapp';
    case Website = 'website';
    case Telegram = 'telegram';
    case Fax = 'fax';
    case Other = 'other';
}
```

Use TitleCase enum keys.

### 11.2 ContactPurpose

```php
enum ContactPurpose: string
{
    case General = 'general';
    case Admin = 'admin';
    case Support = 'support';
    case Billing = 'billing';
    case Sales = 'sales';
    case Registration = 'registration';
    case Media = 'media';
    case Donation = 'donation';
    case Partnership = 'partnership';
    case Emergency = 'emergency';
    case Privacy = 'privacy';
    case Other = 'other';
}
```

### 11.3 SocialPlatform

```php
enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';
    case Linkedin = 'linkedin';
    case X = 'x';
    case Threads = 'threads';
    case Telegram = 'telegram';
    case TelegramChannel = 'telegram_channel';
    case TelegramGroup = 'telegram_group';
    case WhatsappChannel = 'whatsapp_channel';
    case Website = 'website';
    case Other = 'other';
}
```

---

## 12. Normalization Rules

### 12.1 General rules

Normalization must be conservative.

Do not perform external HTTP calls.

Do not fetch social profile pages.

Do not expand short URLs.

Do not verify ownership of handles.

Do not call platform APIs.

### 12.2 Email normalization

Rules:

```txt
- trim whitespace
- lowercase the domain
- lowercase the whole email unless product rules require preserving local case
- reject invalid email by returning null or validation error depending context
```

Recommended helper:

```php
final class NormalizesEmailAddress
{
    public function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $email = mb_strtolower($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
```

### 12.3 URL normalization

Rules:

```txt
- trim whitespace
- empty string becomes null
- if URL has no scheme and looks like a domain, prepend https://
- allow only http/https
- return normalized URL or null
- do not fetch the URL
```

### 12.4 Phone normalization

Rules for v1:

```txt
- trim whitespace
- remove spaces, dashes, parentheses
- preserve leading +
- if country_code is MY and phone starts with 0, convert to +60...
- if value already starts with +, preserve international format
- do not claim E.164 compliance without robust phone library
```

Example:

```txt
Input: 012-345 6789, country MY
Normalized: +60123456789
Display: 012-345 6789 or +60 12-345 6789 depending formatter
```

Do not overbuild phone validation in v1. Malaysian/SEA numbers can be messy.

### 12.5 WhatsApp link generation

For WhatsApp contact method:

```txt
Input value: +60123456789
Generated link: https://wa.me/60123456789
```

Remove `+` in `wa.me` path.

Do not include a default message unless explicitly provided by consumer package.

### 12.6 Social handle normalization

Rules:

```txt
- trim whitespace
- remove leading @ for handle storage
- keep URL separately if provided
- if URL exists and handle is missing, attempt simple extraction only for known platform URL shapes
- do not fetch the URL
- do not require handles for platforms where URLs are enough
```

Example:

```txt
@masjidcontoh -> masjidcontoh
https://instagram.com/masjidcontoh -> handle masjidcontoh, url preserved
```

---

## 13. Actions

All write/orchestration logic must be in Actions.

### 13.1 CreateContactMethodAction

Path:

```txt
packages/contacting/src/Actions/CreateContactMethodAction.php
```

Signature:

```php
public function execute(Model $contactable, ContactMethodData|array $data): ContactMethod
```

Required behavior:

```txt
- Convert array to ContactMethodData.
- Normalize value according to type.
- Apply default public/verified values from config.
- Attach to contactable.
- Assign owner if owner scoping is enabled and current owner exists.
- If isPrimary is true, unset other primary contacts for same contactable/type/purpose.
- Save ContactMethod.
- Return ContactMethod.
```

Do not create duplicate detection in v1 unless clear rules exist.

### 13.2 UpdateContactMethodAction

Signature:

```php
public function execute(ContactMethod $contactMethod, ContactMethodData|array $data): ContactMethod
```

Required behavior:

```txt
- Validate owner/write scope if owner mode is enabled.
- Normalize value.
- Update fields.
- If isPrimary is true, unset other primary contacts for same contactable/type/purpose.
- Return updated ContactMethod.
```

### 13.3 SetPrimaryContactMethodAction

Signature:

```php
public function execute(ContactMethod $contactMethod): ContactMethod
```

Required behavior:

```txt
- Find sibling contact methods with same contactable, type, and purpose.
- Set their is_primary to false.
- Set target is_primary to true.
- Use a DB transaction.
- Enforce owner/write scope.
```

### 13.4 CreateSocialProfileAction

Signature:

```php
public function execute(Model $socialable, SocialProfileData|array $data): SocialProfile
```

Required behavior:

```txt
- Convert array to SocialProfileData.
- Normalize platform, handle, and URL.
- Apply defaults.
- Attach to socialable.
- Assign owner if enabled.
- If isPrimary is true, unset other primary social profiles for same socialable/platform/purpose.
- Save and return SocialProfile.
```

### 13.5 UpdateSocialProfileAction

Same pattern as `UpdateContactMethodAction`.

### 13.6 SetPrimarySocialProfileAction

Same pattern as `SetPrimaryContactMethodAction`, scoped by socialable/platform/purpose.

### 13.7 BuildContactLinksAction

This action generates safe output links from stored contact data.

Signature:

```php
public function execute(iterable $contactMethods): ContactLinksData
```

Or:

```php
public function forContactable(Model $contactable): ContactLinksData
```

Required generated links:

```txt
email   -> mailto:normalized@example.com
phone   -> tel:+60123456789
mobile  -> tel:+60123456789
whatsapp -> https://wa.me/60123456789
website -> https://example.com
telegram -> https://t.me/handle when value is handle; preserve URL when value is URL
```

Do not generate share links. WhatsApp contact link is allowed because it opens a conversation with the entity. WhatsApp share link belongs to sharing/engagement.

### 13.8 CreateContactSnapshotAction

Signature examples:

```php
public function fromContactMethod(Model $snapshotable, ContactMethod $contactMethod, ?string $reason = null): ContactSnapshot

public function fromSocialProfile(Model $snapshotable, SocialProfile $socialProfile, ?string $reason = null): ContactSnapshot

public function fromBundle(Model $snapshotable, iterable $contactMethods, iterable $socialProfiles, ?string $reason = null): Collection
```

Required behavior:

```txt
- Copy current data into snapshot columns/payload.
- Do not store a live dependency as source of truth.
- Preserve public/private state at snapshot time.
- Assign owner if enabled.
- Do not update snapshot later when source changes.
```

---

## 14. Validation Rules

Provide reusable validation examples in docs. Do not force every consumer to use the same FormRequest.

### 14.1 Contact method validation

Suggested rules:

```php
[
    'type' => ['required', 'string', 'max:50'],
    'purpose' => ['nullable', 'string', 'max:50'],
    'label' => ['nullable', 'string', 'max:100'],
    'value' => ['required', 'string', 'max:2048'],
    'country_code' => ['nullable', 'string', 'size:2'],
    'is_primary' => ['boolean'],
    'is_public' => ['boolean'],
    'is_verified' => ['boolean'],
    'metadata' => ['nullable', 'array'],
]
```

Type-specific validation should happen in Actions/normalizers or consumer forms.

### 14.2 Social profile validation

Suggested rules:

```php
[
    'platform' => ['required', 'string', 'max:50'],
    'purpose' => ['nullable', 'string', 'max:50'],
    'label' => ['nullable', 'string', 'max:100'],
    'handle' => ['nullable', 'string', 'max:255'],
    'url' => ['nullable', 'url', 'max:2048'],
    'display_name' => ['nullable', 'string', 'max:255'],
    'external_id' => ['nullable', 'string', 'max:255'],
    'is_primary' => ['boolean'],
    'is_public' => ['boolean'],
    'is_verified' => ['boolean'],
    'metadata' => ['nullable', 'array'],
]
```

Do not hard-block platform hosts in v1. Platform URLs change, regional domains exist, and copied URLs are messy.

---

## 15. Ownership and Multitenancy

Contacting may store tenant-sensitive information.

Examples:

```txt
customer phone number
private organizer email
business WhatsApp number
internal admin contact
non-public ministry contact
```

If owner scoping is enabled:

```txt
- reads must be owner-scoped
- writes must validate owner context
- direct queries must not leak cross-tenant rows
- Filament UI scoping is not security
- background jobs/commands must not rely on ambient web auth
```

Implementation requirements:

```txt
- include owner_type/owner_id columns
- use commerce-support owner primitives if available/required
- do not invent ad hoc owner scoping
- add cross-tenant regression tests
```

If owner scoping is disabled, records may be global. Global writes must still be intentional in code paths that use owner context.

---

## 16. Consumer Package Usage

### 16.1 Institution / Masjid / Organization

```php
use AiArmada\Contacting\Concerns\HasContactMethods;
use AiArmada\Contacting\Concerns\HasSocialProfiles;

final class Institution extends Model
{
    use HasContactMethods;
    use HasSocialProfiles;
}
```

Create contacts:

```php
use AiArmada\Contacting\Actions\CreateContactMethodAction;

app(CreateContactMethodAction::class)->execute($institution, [
    'type' => 'whatsapp',
    'purpose' => 'admin',
    'label' => 'Admin WhatsApp',
    'value' => '+60123456789',
    'country_code' => 'MY',
    'is_primary' => true,
    'is_public' => true,
]);
```

Create social profile:

```php
use AiArmada\Contacting\Actions\CreateSocialProfileAction;

app(CreateSocialProfileAction::class)->execute($institution, [
    'platform' => 'facebook',
    'label' => 'Official Facebook Page',
    'handle' => 'masjidcontoh',
    'url' => 'https://facebook.com/masjidcontoh',
    'is_primary' => true,
    'is_public' => true,
]);
```

### 16.2 Event package

Events should not own contact methods directly unless the event has event-specific contact channels.

Preferred flow:

```txt
Event -> Organizer/Institution -> ContactMethods/SocialProfiles
Event -> ContactSnapshot at publish time
```

When event is approved/published, create snapshots of public organizer contacts if needed:

```php
app(CreateContactSnapshotAction::class)->fromBundle(
    snapshotable: $event,
    contactMethods: $event->organizer->publicContactMethods()->get(),
    socialProfiles: $event->organizer->publicSocialProfiles()->get(),
    reason: 'event_public_contact',
);
```

### 16.3 Customer package

Customer may use:

```txt
email
phone
mobile
whatsapp
```

Do not use social profiles unless product requires customer social identity.

### 16.4 Commerce/support/cashier/tax packages

Most payment/tax packages should not store contacts themselves. They may read `ContactMethodData` or contact methods from customer/vendor profiles.

Do not send social profile data to payment gateways unless provider explicitly requires it.

### 16.5 Addressing package

Addressing should not depend on contacting.

A branch, venue, or institution can use both:

```php
class Venue extends Model
{
    use HasAddresses;
    use HasContactMethods;
    use HasSocialProfiles;
}
```

Do not put phone/email/social columns in `addresses`.

---

## 17. Filament Boundary

Do not create Filament resources inside `aiarmada/contacting`.

If UI is needed later, create:

```txt
aiarmada/filament-contacting
```

That package may provide:

```txt
ContactMethodResource
SocialProfileResource
ContactMethodsRelationManager
SocialProfilesRelationManager
reusable ContactMethodsFormSchema
reusable SocialProfilesFormSchema
```

But Filament is an adapter only.

Domain logic must remain in `aiarmada/contacting` Actions and models.

---

## 18. Documentation Requirements

Create all required docs.

### 18.1 `01-overview.md`

Must explain:

```txt
- what contacting is
- why it exists
- how it differs from addressing and engagement/sharing
- main tables
- main traits
- main Actions
- examples of entities that can use it
```

### 18.2 `02-installation.md`

Must explain:

```txt
- composer install/path repository setup if needed
- publish config
- publish migrations
- run migrations
- optional owner-scoping setup
```

### 18.3 `03-configuration.md`

Must explain:

```txt
- table names
- JSON column type
- defaults
- features
- contact method types
- social platforms
- owner scoping
```

### 18.4 `04-usage.md`

Must include copy-paste examples:

```txt
- add HasContactMethods
- add HasSocialProfiles
- create email contact
- create WhatsApp contact
- create website contact
- create Facebook/Instagram/TikTok profile
- set primary contact
- build contact links
- create event contact snapshot
```

### 18.5 `99-troubleshooting.md`

Must cover:

```txt
- contact not appearing due to owner scope
- primary contact not unique because Action was bypassed
- phone normalization surprises
- social URL not parsed
- public/private visibility confusion
- snapshot not changing after source update
- config JSON type mismatch
```

---

## 19. Factories

Create factories for tests.

### 19.1 ContactMethodFactory

Defaults:

```txt
type: email
purpose: general
label: Admin
value: admin@example.com
normalized_value: admin@example.com
display_value: admin@example.com
is_primary: false
is_public: true
is_verified: false
metadata: []
```

Add states:

```php
email()
phone()
whatsapp()
website()
primary()
private()
verified()
```

### 19.2 SocialProfileFactory

Defaults:

```txt
platform: facebook
purpose: general
handle: example
url: https://facebook.com/example
is_public: true
is_primary: false
is_verified: false
metadata: []
```

Add states:

```php
facebook()
instagram()
tiktok()
youtube()
primary()
private()
verified()
```

### 19.3 ContactSnapshotFactory

Defaults:

```txt
snapshot_type: contact_method
reason: test
channel: email
value: admin@example.com
payload: []
metadata: []
```

---

## 20. Tests

Use Pest.

Every test command must include `--parallel`.

### 20.1 Migration tests

Test:

```txt
- contact_methods table is created
- social_profiles table is created
- contact_snapshots table is created
- UUID id columns exist
- morph columns exist
- owner columns exist if required
- JSON columns use configured type where testable
- no forbidden constrained/cascade patterns in migrations
```

### 20.2 Model tests

Test:

```txt
- getTable() respects config
- contactable morph works
- socialable morph works
- casts work
- public/primary/verified scopes work
```

### 20.3 Trait tests

Create a dummy model in tests that uses traits.

Test:

```txt
- model can have many contact methods
- model can have many social profiles
- primaryContactMethod works
- primarySocialProfile works
- publicContactMethods filters private records
- publicSocialProfiles filters private records
```

### 20.4 Action tests

Test:

```txt
- CreateContactMethodAction creates contact method
- CreateContactMethodAction normalizes email
- CreateContactMethodAction normalizes MY phone
- CreateContactMethodAction handles WhatsApp
- CreateContactMethodAction sets primary and unsets sibling primary
- UpdateContactMethodAction updates and normalizes
- SetPrimaryContactMethodAction unsets sibling primary
- CreateSocialProfileAction creates social profile
- CreateSocialProfileAction normalizes @handle
- CreateSocialProfileAction preserves URL
- SetPrimarySocialProfileAction unsets sibling primary
- BuildContactLinksAction builds mailto/tel/wa.me/website links
```

### 20.5 Snapshot tests

Test:

```txt
- snapshot from contact method copies data
- snapshot from social profile copies data
- snapshot bundle creates multiple snapshots
- changing source later does not mutate existing snapshot
```

### 20.6 Owner-scoping tests

If owner scoping is enabled:

```txt
- owner A cannot read owner B contact methods
- owner A cannot update owner B contact methods
- owner A cannot read owner B social profiles
- owner A cannot update owner B social profiles
- global context behavior is explicit
```

---

## 21. Verification Commands

Run only package-scoped commands.

```bash
./vendor/bin/pest --parallel packages/contacting/tests
```

```bash
./vendor/bin/phpstan analyse packages/contacting/src --level=6
```

Run Pint on changed package files only:

```bash
./vendor/bin/pint packages/contacting/src packages/contacting/database packages/contacting/tests
```

Check forbidden DB patterns:

```bash
rg -n -- "constrained\(|cascadeOnDelete\(" packages/contacting/database packages/contacting/src
```

Check config reads:

```bash
rg -n -- "config\('contacting\." packages/contacting/src packages/contacting/config packages/contacting/database
```

Check no accidental HTTP/API fetching:

```bash
rg -n -- "Http::|curl_|file_get_contents\(|Guzzle|Client\(" packages/contacting/src
```

---

## 22. Multi-Agent Work Split

Agents must not overlap files unless explicitly coordinating.

### Agent A — Package Skeleton, Config, Docs Routing

Owns:

```txt
packages/contacting/composer.json
packages/contacting/CONTEXT.md
packages/contacting/config/contacting.php
packages/contacting/src/ContactingServiceProvider.php
```

Checklist:

```txt
- [ ] Create package skeleton.
- [ ] Register config publishing.
- [ ] Register migrations publishing/loading according to sibling style.
- [ ] Ensure config keys are minimal and read by code.
- [ ] Create CONTEXT.md with required frontmatter and sections.
```

### Agent B — Database and Models

Owns:

```txt
packages/contacting/database/migrations/*
packages/contacting/src/Models/*
packages/contacting/database/factories/*
```

Checklist:

```txt
- [ ] Create migrations with UUID primary keys.
- [ ] No DB constraints/cascades.
- [ ] Use config table names.
- [ ] Use config JSON column type.
- [ ] Create models with HasUuids and getTable().
- [ ] Add casts and relationships.
- [ ] Create factories.
```

### Agent C — Data, Enums, Normalizers

Owns:

```txt
packages/contacting/src/Data/*
packages/contacting/src/Enums/*
packages/contacting/src/Support/*
packages/contacting/src/Contracts/*
```

Checklist:

```txt
- [ ] Create ContactMethodData.
- [ ] Create SocialProfileData.
- [ ] Create ContactLinksData.
- [ ] Create ContactSnapshotData.
- [ ] Create enums or constants.
- [ ] Create conservative normalizers.
- [ ] Ensure no external HTTP calls.
```

### Agent D — Actions and Traits

Owns:

```txt
packages/contacting/src/Actions/*
packages/contacting/src/Concerns/*
```

Checklist:

```txt
- [ ] Create HasContactMethods.
- [ ] Create HasSocialProfiles.
- [ ] Create create/update/set-primary Actions.
- [ ] Create BuildContactLinksAction.
- [ ] Create snapshot Action.
- [ ] Keep orchestration out of models/traits.
- [ ] Enforce owner/write guard if owner mode is enabled.
```

### Agent E — Tests

Owns:

```txt
packages/contacting/tests/*
```

Checklist:

```txt
- [ ] Add migration tests.
- [ ] Add model tests.
- [ ] Add trait tests.
- [ ] Add Action tests.
- [ ] Add normalizer tests.
- [ ] Add snapshot tests.
- [ ] Add owner-scope tests if applicable.
- [ ] Run Pest with --parallel.
```

### Agent F — Documentation

Owns:

```txt
packages/contacting/docs/*
```

Checklist:

```txt
- [ ] Write overview.
- [ ] Write installation.
- [ ] Write configuration.
- [ ] Write usage with copy-paste examples.
- [ ] Write troubleshooting.
- [ ] Clearly state sharing is not in this package.
- [ ] Clearly state addresses are not in this package.
```

### Agent G — QC / Auditor

Owns no implementation files unless fixing issues after review.

Checklist:

```txt
- [ ] Run targeted verification commands.
- [ ] Search forbidden migration patterns.
- [ ] Search accidental HTTP/API calls.
- [ ] Confirm no Filament code in core package.
- [ ] Confirm no sharing/engagement logic in contacting.
- [ ] Confirm docs match behavior.
- [ ] Confirm package can work with multiple entity types.
```

---

## 23. Acceptance Criteria

The package is complete when:

```txt
- Package skeleton exists at packages/contacting.
- Config file exists and is published/loaded.
- Required docs exist with frontmatter.
- CONTEXT.md exists with required frontmatter and sections.
- contact_methods table migration exists.
- social_profiles table migration exists.
- contact_snapshots table migration exists or explicitly documented as disabled by config.
- All migrations use UUID primary keys.
- No DB constraints/cascades are added.
- JSON columns use config('contacting.database.json_column_type').
- Models use HasUuids.
- Models implement getTable() from config.
- Models do not use SoftDeletes.
- HasContactMethods trait works.
- HasSocialProfiles trait works.
- Create/update/set-primary Actions work.
- Contact/social normalizers work without external HTTP calls.
- Contact link builder generates mailto/tel/wa.me/website links.
- Snapshots preserve historical contact/social data.
- Owner-scoping behavior is safe if enabled.
- Tests pass with --parallel.
- PHPStan level 6 passes for package src.
- Pint is run only for changed package files.
- Docs clearly explain boundaries from addressing and sharing.
```

---

## 24. Example End-to-End Flow

### 24.1 Institution setup

```php
use AiArmada\Contacting\Actions\CreateContactMethodAction;
use AiArmada\Contacting\Actions\CreateSocialProfileAction;

app(CreateContactMethodAction::class)->execute($institution, [
    'type' => 'email',
    'purpose' => 'admin',
    'label' => 'Admin Email',
    'value' => 'admin@masjidcontoh.my',
    'is_primary' => true,
    'is_public' => true,
]);

app(CreateContactMethodAction::class)->execute($institution, [
    'type' => 'whatsapp',
    'purpose' => 'admin',
    'label' => 'Admin WhatsApp',
    'value' => '012-345 6789',
    'country_code' => 'MY',
    'is_primary' => true,
    'is_public' => true,
]);

app(CreateSocialProfileAction::class)->execute($institution, [
    'platform' => 'facebook',
    'label' => 'Official Facebook Page',
    'handle' => '@masjidcontoh',
    'url' => 'https://facebook.com/masjidcontoh',
    'is_primary' => true,
    'is_public' => true,
]);
```

### 24.2 Public links

```php
use AiArmada\Contacting\Actions\BuildContactLinksAction;

$links = app(BuildContactLinksAction::class)->forContactable($institution);

$mailto = $links->mailtoUrl;
$whatsapp = $links->whatsappUrl;
```

### 24.3 Event snapshot

```php
use AiArmada\Contacting\Actions\CreateContactSnapshotAction;

app(CreateContactSnapshotAction::class)->fromBundle(
    snapshotable: $event,
    contactMethods: $institution->publicContactMethods()->get(),
    socialProfiles: $institution->publicSocialProfiles()->get(),
    reason: 'event_public_contact',
);
```

If the institution changes WhatsApp later, the event snapshot remains unchanged.

---

## 25. Final Rule

Do not make `contacting` a dumping ground.

```txt
Address belongs to addressing.
Contact/social identity belongs to contacting.
Share actions belong to engagement/sharing.
Notifications belong to Laravel notifications or notification-specific packages.
```

Same entity can use all of them, but each package must own only its correct domain.

Kalau semua masuk satu package, nanti `contacting` jadi Yellow Pages, social media manager, postman, GPS, and wedding planner sekali. Jangan. 😂
