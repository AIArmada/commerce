<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    bindTaxOwner(null);
});

function bindTaxOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('blocks cross-tenant reads and writes when owner scoping is enabled', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    config()->set('tax.features.zone_resolution.unknown_zone_behavior', 'zero');

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-x@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-x@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerA);

    $zoneA = TaxZone::query()->create([
        'name' => 'Zone A',
        'code' => 'ZA',
        'is_active' => true,
        'is_default' => true,
    ]);

    TaxRate::query()->create([
        'zone_id' => $zoneA->id,
        'name' => 'Rate A',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZB',
        'is_active' => true,
        'is_default' => true,
    ]);

    bindTaxOwner($ownerA);

    $calculator = new TaxCalculator;

    $result = $calculator->calculateTax(10000, 'standard', $zoneB->id);

    expect($result->zoneId)
        ->toBe($zoneA->id);

    expect(fn () => TaxRate::query()->create([
        'zone_id' => $zoneB->id,
        'name' => 'Cross-tenant rate',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]))
        ->toThrow(AuthorizationException::class);
});

it('blocks deleting a global tax zone while owned rates exist', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-delete-global@example.com',
        'password' => 'secret',
    ]);

    $globalZone = OwnerContext::withOwner(null, fn () => TaxZone::query()->create([
        'name' => 'Global Zone',
        'code' => 'GLOBAL-ZONE-DELETE',
        'is_active' => true,
        'is_default' => false,
    ]));

    bindTaxOwner($ownerA);

    TaxRate::query()->create([
        'zone_id' => $globalZone->id,
        'name' => 'Owned Rate',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]);

    bindTaxOwner(null);

    expect(fn () => $globalZone->delete())
        ->toThrow(AuthorizationException::class);
});

it('blocks creating an exemption referencing an out-of-scope zone', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-exemption-zone@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-exemption-zone@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZONE-B-EXEMPTION',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerA);

    expect(fn () => TaxExemption::query()->create([
        'exemptable_type' => 'App\\Models\\Customer',
        'exemptable_id' => 'customer-uuid-exemption',
        'tax_zone_id' => $zoneB->id,
        'reason' => 'Should be blocked',
        'status' => 'approved',
    ]))
        ->toThrow(AuthorizationException::class);
});

it('blocks creating an exemption referencing an out-of-scope exemptable entity', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-exemption-exemptable@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-exemption-exemptable@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerB);

    $classB = TaxClass::query()->create([
        'name' => 'Owner B Class',
        'slug' => 'owner-b-class-exemption',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerA);

    expect(fn () => TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classB->id,
        'reason' => 'Should be blocked by owner write guard',
        'status' => 'approved',
    ]))
        ->toThrow(AuthorizationException::class);
});

it('only nulls tax rate classes for the same owner when deleting a tax class', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-class-delete@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-class-delete@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerA);

    $zoneA = TaxZone::query()->create([
        'name' => 'Zone A',
        'code' => 'ZA-CLASS-DELETE',
        'is_active' => true,
    ]);

    $classA = TaxClass::query()->create([
        'name' => 'Shared Class A',
        'slug' => 'shared-class',
        'is_active' => true,
    ]);

    $rateA = TaxRate::query()->create([
        'zone_id' => $zoneA->id,
        'name' => 'Rate A',
        'rate' => 600,
        'tax_class' => 'shared-class',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZB-CLASS-DELETE',
        'is_active' => true,
    ]);

    $rateB = TaxRate::query()->create([
        'zone_id' => $zoneB->id,
        'name' => 'Rate B',
        'rate' => 700,
        'tax_class' => 'shared-class',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerA);
    $classA->delete();

    $updatedRateA = TaxRate::query()
        ->withoutOwnerScope()
        ->whereKey($rateA->id)
        ->firstOrFail();

    $updatedRateB = TaxRate::query()
        ->withoutOwnerScope()
        ->whereKey($rateB->id)
        ->firstOrFail();

    expect($updatedRateA->tax_class)->toBe('standard')
        ->and($updatedRateB->tax_class)->toBe('shared-class');
});

it('blocks deleting a tax class outside the current owner scope', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-delete-class-scope@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-delete-class-scope@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerA);

    $classA = TaxClass::query()->create([
        'name' => 'Owner A Delete Protected Class',
        'slug' => 'owner-a-delete-protected-class',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerB);

    $crossTenantClass = TaxClass::query()
        ->withoutOwnerScope()
        ->whereKey($classA->id)
        ->firstOrFail();

    expect(fn () => $crossTenantClass->delete())
        ->toThrow(AuthorizationException::class);

    $stillExists = TaxClass::query()
        ->withoutOwnerScope()
        ->whereKey($classA->id)
        ->exists();

    expect($stillExists)->toBeTrue();
});

it('does not throw when updating a tax rate unrelated field without changing zone_id', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Owner Update Guard',
        'email' => 'tax-owner-rate-update@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($owner);

    $zone = TaxZone::query()->create([
        'name' => 'Zone Update',
        'code' => 'ZU-RATE',
        'is_active' => true,
    ]);

    $rate = TaxRate::query()->create([
        'zone_id' => $zone->id,
        'name' => 'Rate Update Test',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]);

    // Updating a non-zone_id field must NOT re-validate zone accessibility
    expect(fn () => $rate->update(['name' => 'Rate Updated']))
        ->not->toThrow(\Exception::class);

    expect($rate->fresh()->name)->toBe('Rate Updated');
});

it('does not throw when updating a tax exemption unrelated field without changing tax_zone_id', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Owner Exemption Update Guard',
        'email' => 'tax-owner-exemption-update@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($owner);

    $zone = TaxZone::query()->create([
        'name' => 'Zone Exemption Update',
        'code' => 'ZU-EXEMPT',
        'is_active' => true,
    ]);

    $exemption = TaxExemption::query()->create([
        'exemptable_type' => 'App\\Models\\Customer',
        'exemptable_id' => 'cust-uuid-update',
        'tax_zone_id' => $zone->id,
        'reason' => 'Original reason',
        'status' => 'approved',
    ]);

    // Updating a non-zone field must NOT re-validate zone accessibility
    expect(fn () => $exemption->update(['reason' => 'Updated reason']))
        ->not->toThrow(\Exception::class);

    expect($exemption->fresh()->reason)->toBe('Updated reason');
});

it('blocks promotion of persisted global tax records into owner scope', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Owner Promote Guard',
        'email' => 'tax-owner-promote-guard@example.com',
        'password' => 'secret',
    ]);

    [$globalZone, $globalClass, $globalRate, $globalExemption] = OwnerContext::withOwner(null, function (): array {
        $zone = TaxZone::query()->create([
            'name' => 'Global Zone Promote',
            'code' => 'GLOBAL-PROMOTE-ZONE',
            'is_active' => true,
            'is_default' => false,
        ]);

        $class = TaxClass::query()->create([
            'name' => 'Global Class Promote',
            'slug' => 'global-class-promote',
            'is_active' => true,
        ]);

        $rate = TaxRate::query()->create([
            'zone_id' => $zone->id,
            'name' => 'Global Rate Promote',
            'rate' => 600,
            'tax_class' => $class->slug,
            'is_active' => true,
        ]);

        $exemption = TaxExemption::query()->create([
            'exemptable_type' => TaxClass::class,
            'exemptable_id' => $class->id,
            'reason' => 'Global exemption',
            'status' => 'approved',
            'tax_zone_id' => $zone->id,
        ]);

        return [$zone, $class, $rate, $exemption];
    });

    bindTaxOwner($owner);

    expect(fn () => $globalZone->update(['name' => 'Updated by Owner']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $globalClass->update(['name' => 'Updated by Owner']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $globalRate->update(['name' => 'Updated by Owner']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $globalExemption->update(['reason' => 'Updated by Owner']))
        ->toThrow(AuthorizationException::class);

    $reloadedZone = TaxZone::query()->withoutOwnerScope()->whereKey($globalZone->id)->firstOrFail();
    $reloadedClass = TaxClass::query()->withoutOwnerScope()->whereKey($globalClass->id)->firstOrFail();
    $reloadedRate = TaxRate::query()->withoutOwnerScope()->whereKey($globalRate->id)->firstOrFail();
    $reloadedExemption = TaxExemption::query()->withoutOwnerScope()->whereKey($globalExemption->id)->firstOrFail();

    expect($reloadedZone->owner_type)->toBeNull()->and($reloadedZone->owner_id)->toBeNull();
    expect($reloadedClass->owner_type)->toBeNull()->and($reloadedClass->owner_id)->toBeNull();
    expect($reloadedRate->owner_type)->toBeNull()->and($reloadedRate->owner_id)->toBeNull();
    expect($reloadedExemption->owner_type)->toBeNull()->and($reloadedExemption->owner_id)->toBeNull();
});
