<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Actions\DeleteTaxClassesAction;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function bindTaxOwnerForScoping(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
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

        $global = OwnerContext::withOwner(null, fn () => TaxZone::query()->create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL_ZONE',
        ]));

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

        $global = OwnerContext::withOwner(null, fn () => TaxClass::query()->create([
            'name' => 'Global Class',
            'slug' => 'global-class',
        ]));

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

it('deletes tax classes when owner scoping is disabled', function (): void {
    config()->set('tax.features.owner.enabled', false);
    $class = TaxClass::query()->create(['name' => 'Unused Class', 'slug' => 'unused-class']);

    app(DeleteTaxClassesAction::class)->execute([$class->id]);

    expect(TaxClass::query()->whereKey($class->id)->exists())->toBeFalse();
});

it('rejects an entire bulk delete when one tax class belongs to another owner', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', true);
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    bindTaxOwnerForScoping($ownerA);
    $owned = TaxClass::query()->create(['name' => 'Owned Class', 'slug' => 'owned-bulk-class']);

    bindTaxOwnerForScoping($ownerB);
    $foreign = TaxClass::query()->create(['name' => 'Foreign Class', 'slug' => 'foreign-bulk-class']);

    bindTaxOwnerForScoping($ownerA);
    expect(fn () => app(DeleteTaxClassesAction::class)->execute([$owned->id, $foreign->id]))
        ->toThrow(AuthorizationException::class);
    expect(TaxClass::query()->whereKey($owned->id)->exists())->toBeTrue();

    bindTaxOwnerForScoping($ownerB);
    expect(TaxClass::query()->whereKey($foreign->id)->exists())->toBeTrue();
});
