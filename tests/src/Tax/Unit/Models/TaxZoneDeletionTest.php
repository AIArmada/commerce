<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Models;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxZoneDeletionTest extends TaxTestCase
{
    use RefreshDatabase;

    private function bindOwner(?Model $owner): void
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

    public function test_deleting_zone_cascades_to_rates_without_owner_scoping(): void
    {
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
    }

    public function test_deleting_owned_zone_deletes_owned_rates_only(): void
    {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Tax Owner',
            'email' => 'tax-zone-owner@example.com',
            'password' => 'secret',
        ]);

        $this->bindOwner($owner);

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
    }

    public function test_deleting_global_zone_with_owned_rates_throws(): void
    {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Rate Owner',
            'email' => 'rate-owner@example.com',
            'password' => 'secret',
        ]);

        $this->bindOwner(null);

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

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Cannot delete a global tax zone while owned rates exist.');

        OwnerContext::withOwner(null, fn () => $globalZone->delete());
    }

    public function test_deleting_global_zone_deletes_global_rates_only(): void
    {
        config(['tax.features.owner.enabled' => true]);

        $this->bindOwner(null);

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
    }

    public function test_deleting_owned_zone_without_owner_context_throws(): void
    {
        config(['tax.features.owner.enabled' => true]);

        $owner = User::query()->create([
            'name' => 'Zone Owner',
            'email' => 'zone-owner-del@example.com',
            'password' => 'secret',
        ]);

        $this->bindOwner($owner);

        $zone = TaxZone::create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-DEL',
            'is_active' => true,
        ]);

        $this->bindOwner(null);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('A matching owner context is required to delete owned AIArmada\Tax\Models\TaxZone records.');

        $freshZone = TaxZone::withoutOwnerScope()->find($zone->id);
        $freshZone->delete();
    }

    public function test_deleting_zone_outside_owner_scope_throws(): void
    {
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

        $this->bindOwner($ownerA);

        $zoneA = TaxZone::create([
            'name' => 'Owner A Zone',
            'code' => 'OWNER-A',
            'is_active' => true,
        ]);

        $this->bindOwner($ownerB);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Cross-owner delete blocked for AIArmada\Tax\Models\TaxZone.');

        $freshZone = TaxZone::withoutOwnerScope()->find($zoneA->id);
        $freshZone->delete();
    }
}
