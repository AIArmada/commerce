<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Actions\DeleteTaxZonesAction;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

$bindOwner = function (?Model $owner): void {
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });
};

describe('TaxZoneDeletion', function () use ($bindOwner): void {
    it('deleting zone cascades to rates without owner scoping', function (): void {
        config(['tax.features.owner.enabled' => false]);

        $zone = TaxZone::create([
            'name' => 'Cascade Test',
            'code' => 'CASCADE',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rate 1',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rate 2',
            'rate' => 800,
            'tax_class' => 'reduced',
            'is_active' => true,
        ]);

        $this->assertCount(2, TaxRate::where('zone_id', $zone->id)->get());

        $zone->delete();

        $this->assertCount(0, TaxRate::where('zone_id', $zone->id)->get());
    });

    it('preserves zones referenced by exemptions and does not delete their rates', function (): void {
        config(['tax.features.owner.enabled' => false]);

        $zone = TaxZone::factory()->create();
        $rate = TaxRate::factory()->create(['zone_id' => $zone->id]);
        $exemption = TaxExemption::factory()->create(['tax_zone_id' => $zone->id]);

        expect(fn () => app(DeleteTaxZonesAction::class)->execute([$zone->id]))
            ->toThrow(ValidationException::class);

        expect($zone->fresh())->not->toBeNull()
            ->and($rate->fresh())->not->toBeNull()
            ->and($exemption->fresh()->tax_zone_id)->toBe($zone->id);
    });

    it('deleting owned zone deletes owned rates only', function () use ($bindOwner): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Tax Owner',
            'email' => 'tax-zone-owner@example.com',
            'password' => 'secret',
        ]);

        $bindOwner($owner);

        $zone = TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Owned Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $this->assertCount(1, TaxRate::withoutOwnerScope()->where('zone_id', $zone->id)->get());

        $zone->delete();

        $this->assertCount(0, TaxRate::withoutOwnerScope()->where('zone_id', $zone->id)->get());
    });

    it('deleting global zone with owned rates throws', function () use ($bindOwner): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Rate Owner',
            'email' => 'rate-owner@example.com',
            'password' => 'secret',
        ]);

        $bindOwner(null);

        $globalZone = OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL',
            'is_active' => true,
        ]));

        DB::table((new TaxRate)->getTable())->insert([
            'id' => (string) Str::uuid(),
            'zone_id' => $globalZone->id,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'name' => 'Owned Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_compound' => false,
            'is_shipping' => false,
            'priority' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        OwnerContext::withOwner(null, fn () => $globalZone->delete());
    })->throws(AuthorizationException::class, 'Cannot delete a global tax zone while owned rates exist.');

    it('deleting global zone deletes global rates only', function () use ($bindOwner): void {
        config(['tax.features.owner.enabled' => true]);

        $bindOwner(null);

        $globalZone = OwnerContext::withOwner(null, fn () => TaxZone::create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL-DEL',
            'is_active' => true,
        ]));

        OwnerContext::withOwner(null, fn () => TaxRate::create([
            'zone_id' => $globalZone->id,
            'name' => 'Global Rate',
            'rate' => 500,
            'tax_class' => 'standard',
            'is_active' => true,
        ]));

        $this->assertCount(1, TaxRate::withoutOwnerScope()->where('zone_id', $globalZone->id)->get());

        OwnerContext::withOwner(null, fn () => $globalZone->delete());

        $this->assertCount(0, TaxRate::withoutOwnerScope()->where('zone_id', $globalZone->id)->get());
    });

    it('deleting owned zone without owner context throws', function () use ($bindOwner): void {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Zone Owner',
            'email' => 'zone-owner-del@example.com',
            'password' => 'secret',
        ]);

        $bindOwner($owner);

        $zone = TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-DEL',
            'is_active' => true,
        ]);

        $bindOwner(null);

        $freshZone = TaxZone::withoutOwnerScope()->find($zone->id);
        $freshZone->delete();
    })->throws(AuthorizationException::class, 'A matching owner context is required to delete owned AIArmada\Tax\Models\TaxZone records.');

    it('deleting zone outside owner scope throws', function () use ($bindOwner): void {
        config(['tax.features.owner.enabled' => true]);

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-del@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b-del@example.com',
            'password' => 'secret',
        ]);

        $bindOwner($ownerA);

        $zoneA = TaxZone::create([
            'name' => 'Owner A Zone',
            'code' => 'OWNER-A',
            'is_active' => true,
        ]);

        $bindOwner($ownerB);

        $freshZone = TaxZone::withoutOwnerScope()->find($zoneA->id);
        $freshZone->delete();
    })->throws(AuthorizationException::class, 'Cross-owner delete blocked for AIArmada\Tax\Models\TaxZone.');
});

it('rolls back dependent rate deletion when a tax zone deletion is cancelled', function (): void {
    config(['tax.features.owner.enabled' => false]);
    $zone = TaxZone::factory()->create();
    $rate = TaxRate::factory()->create(['zone_id' => $zone->id]);

    TaxZone::deleting(fn (TaxZone $record): ?bool => $record->is($zone) ? false : null);

    expect($zone->delete())->toBeFalse()
        ->and($zone->fresh())->not->toBeNull()
        ->and($rate->fresh())->not->toBeNull();
});

it('rolls back the entire bulk zone deletion when a later deletion is cancelled', function (): void {
    config(['tax.features.owner.enabled' => false]);
    $zones = collect([TaxZone::factory()->create(), TaxZone::factory()->create()])->sortBy('id')->values();
    $first = $zones[0];
    $last = $zones[1];
    $rate = TaxRate::factory()->create(['zone_id' => $first->id]);

    TaxZone::deleting(fn (TaxZone $record): ?bool => $record->is($last) ? false : null);

    expect(fn () => app(DeleteTaxZonesAction::class)->execute([$first->id, $last->id]))
        ->toThrow(RuntimeException::class, 'Tax zone deletion was cancelled.');

    expect($first->fresh())->not->toBeNull()
        ->and($last->fresh())->not->toBeNull()
        ->and($rate->fresh())->not->toBeNull();
});
