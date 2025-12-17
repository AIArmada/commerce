<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function bindTaxOwnerForScoping(?Model $owner): void
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

describe('Tax owner scoping', function (): void {
    beforeEach(function (): void {
        config()->set('tax.features.owner.enabled', true);
    });

    it('scopes TaxZone owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'tax-owner-a@example.com',
            'password' => 'secret',
        ]);

        bindTaxOwnerForScoping(null);

        $global = TaxZone::query()->create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL_ZONE',
        ]);

        bindTaxOwnerForScoping($ownerA);

        $owned = TaxZone::query()->create([
            'name' => 'Owned Zone',
            'code' => 'OWNED_ZONE',
        ]);

        $corruptId = (string) Str::uuid();

        DB::table((new TaxZone)->getTable())->insert([
            'id' => $corruptId,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
            'name' => 'Corrupt Zone',
            'code' => 'CORRUPT_ZONE',
            'type' => 'country',
            'priority' => 0,
            'is_default' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = TaxZone::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corruptId);
    });

    it('scopes TaxClass owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'tax-owner-a-2@example.com',
            'password' => 'secret',
        ]);

        bindTaxOwnerForScoping(null);

        $global = TaxClass::query()->create([
            'name' => 'Global Class',
            'slug' => 'global-class',
        ]);

        bindTaxOwnerForScoping($ownerA);

        $owned = TaxClass::query()->create([
            'name' => 'Owned Class',
            'slug' => 'owned-class',
        ]);

        $corruptId = (string) Str::uuid();

        DB::table((new TaxClass)->getTable())->insert([
            'id' => $corruptId,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
            'name' => 'Corrupt Class',
            'slug' => 'corrupt-class',
            'description' => null,
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = TaxClass::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corruptId);
    });
});
