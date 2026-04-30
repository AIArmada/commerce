---
title: Multi-Tenancy
---

# Multi-Tenancy (Owner Scoping)

The affiliates package fully supports multi-tenant architectures using the `commerce-support` owner scoping system.

## Enabling Owner Mode

```php
// config/affiliates.php
'owner' => [
    'enabled' => env('AFFILIATES_OWNER_ENABLED', false),
    'include_global' => env('AFFILIATES_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('AFFILIATES_OWNER_AUTO_ASSIGN', true),
],
```

## How It Works

When owner mode is enabled:

1. **All models** with `owner_type` and `owner_id` columns are automatically scoped
2. **Queries** only return records belonging to the current owner
3. **New records** automatically get the current owner assigned
4. **Cross-tenant access** is prevented by default

## Owner Resolver

The owner is resolved via `commerce-support`'s `OwnerResolverInterface`:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;

// In your AppServiceProvider
$this->app->bind(OwnerResolverInterface::class, function () {
    return new class implements OwnerResolverInterface {
        public function resolve(): ?Model
        {
            // Return the current tenant/owner
            return auth()->user()?->currentTeam;
        }
    };
});

// The resolved owner is available via:
$owner = OwnerContext::resolve();
```

## Scoped Models

All owner-aware models use two traits:

```php
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;

class Affiliate extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';
}
```

### Owner-Scoped Models

- `Affiliate`
- `AffiliateAttribution`
- `AffiliateConversion`
- `AffiliatePayout`
- `AffiliateProgram`
- `AffiliateRank`
- `AffiliateCommissionTemplate`
- `AffiliateTrainingModule`

### Derived Models (Scope via Affiliate)

Some models don't have direct owner columns but are scoped through their parent affiliate:

```php
use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;

class AffiliateFraudSignal extends Model
{
    use ScopesByAffiliateOwner;

    // Scoped via affiliate_id relationship
}
```

These models:
- `AffiliateFraudSignal`
- `AffiliateBalance`
- `AffiliateDailyStat`
- `AffiliatePayoutMethod`
- `AffiliatePayoutHold`
- `AffiliateLink`
- `AffiliateCommissionRule`
- `AffiliateVolumeTier`

## Querying with Owner Scope

```php
use AIArmada\Affiliates\Models\Affiliate;

// Automatically scoped to current owner
$affiliates = Affiliate::query()->get();

// Explicit owner scoping
$affiliates = Affiliate::forOwner($team)->get();

// Include global records (owner_id = null)
$affiliates = Affiliate::forOwner($team, includeGlobal: true)->get();

// Global records only
$affiliates = Affiliate::globalOnly()->get();

// Bypass owner scope (use with caution!)
$all = Affiliate::withoutOwnerScope()->get();
```

## Cross-Tenant Lookups

Some operations require looking up affiliates across tenants (e.g., validating an affiliate code from a URL):

```php
use AIArmada\Affiliates\Services\AffiliateService;

$service = app(AffiliateService::class);

// Normal lookup (scoped)
$affiliate = $service->findByCode('PARTNER42');

// Cross-tenant lookup (for validation only)
$affiliate = $service->findByCodeWithoutOwnerScope('PARTNER42');
```

## Auto-Assignment

When `auto_assign_on_create` is true, new records automatically get the current owner:

```php
// config: auto_assign_on_create = true

$affiliate = Affiliate::create([
    'code' => 'NEWPARTNER',
    'name' => 'New Partner',
    // owner_type and owner_id automatically set from OwnerContext::resolve()
]);
```

To create a global record (no owner):

```php
$affiliate = Affiliate::create([
    'code' => 'GLOBALPARTNER',
    'name' => 'Global Partner',
    'owner_type' => null,
    'owner_id' => null,
]);
```

## Global Records

Global records (`owner_id = null`) can be shared across all tenants:

```php
// Create global program available to all tenants
$program = AffiliateProgram::create([
    'name' => 'Platform-Wide Program',
    'slug' => 'platform-wide',
    'owner_type' => null,
    'owner_id' => null,
]);

// Include global in queries
$programs = AffiliateProgram::forOwner($team, includeGlobal: true)->get();
```

Configure default behavior:

```php
'owner' => [
    'include_global' => false, // Don't include global by default
],
```

## Validation

Always validate foreign keys belong to the current owner:

```php
// In a Filament form
Select::make('affiliate_program_id')
    ->options(fn () => AffiliateProgram::forOwner()
        ->pluck('name', 'id'))
    ->required()
    // Validate the ID belongs to current owner
    ->rules([
        fn () => function ($attribute, $value, $fail) {
            if (!AffiliateProgram::forOwner()->whereKey($value)->exists()) {
                $fail('Invalid program selected.');
            }
        },
    ]);
```

## Filament Resources

Filament resources automatically apply owner scoping via `getEloquentQuery()`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->forOwner();
}
```

## Jobs and Commands

For background jobs and commands, enter the owner context with `OwnerContext::withOwner(...)`:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

class ProcessAffiliatePayouts implements ShouldQueue
{
    public function __construct(
        private string $ownerId,
        private string $ownerType,
    ) {}

    public function handle(): void
    {
        $owner = $this->ownerType::find($this->ownerId);

        OwnerContext::withOwner($owner, function (): void {
            $affiliates = Affiliate::query()->get();

            // Process payouts...
        });
    }
}
```

`OwnerContext::setForRequest()` is reserved for HTTP middleware/framework integrations during an active request. Non-HTTP surfaces should use `OwnerContext::withOwner(...)` so owner overrides are always restored safely.

When reconstructing owner context from raw rows or payloads, prefer the shared tuple helpers and fail closed on malformed values:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;

$parsed = OwnerTupleParser::fromTypeAndId($payload['owner_type'] ?? null, $payload['owner_id'] ?? null);

OwnerContext::withOwner($parsed->toOwnerModel(), function (): void {
    Affiliate::query()->get();
});
```

## Testing Multi-Tenancy

```php
it('scopes affiliates to owner', function () {
    config(['affiliates.owner.enabled' => true]);

    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();

    // Set owner context
    app()->instance(OwnerResolverInterface::class, new class($teamA) implements OwnerResolverInterface {
        public function __construct(private Model $owner) {}
        public function resolve(): ?Model { return $this->owner; }
    });

    $affiliateA = Affiliate::create([
        'code' => 'TEAM-A',
        'name' => 'Team A Affiliate',
    ]);

    // Create affiliate for Team B directly
    $affiliateB = Affiliate::create([
        'code' => 'TEAM-B',
        'name' => 'Team B Affiliate',
        'owner_type' => $teamB->getMorphClass(),
        'owner_id' => $teamB->getKey(),
    ]);

    // Query should only return Team A's affiliate
    $affiliates = Affiliate::query()->get();

    expect($affiliates)->toHaveCount(1)
        ->and($affiliates->first()->code)->toBe('TEAM-A');
});
```
