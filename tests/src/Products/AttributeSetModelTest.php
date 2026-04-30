<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Support\Fixtures\TestOwner;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

describe('AttributeSet Model', function (): void {
    beforeEach(function (): void {
        Schema::dropIfExists('test_owners');

        Schema::create('test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        config()->set('products.features.owner.enabled', true);
        config()->set('products.features.owner.auto_assign_on_create', false);

        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });
    });

    it('scopes setAsDefault() updates to the same owner', function (): void {
        $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

        $ownerASet1 = OwnerContext::withOwner($ownerA, static function () use ($ownerA): AttributeSet {
            return AttributeSet::create([
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
                'name' => 'Owner A Default',
                'code' => 'owner-a-default-' . Str::uuid(),
                'is_default' => true,
            ]);
        });

        $ownerASet2 = OwnerContext::withOwner($ownerA, static function () use ($ownerA): AttributeSet {
            return AttributeSet::create([
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
                'name' => 'Owner A New Default',
                'code' => 'owner-a-new-default-' . Str::uuid(),
                'is_default' => false,
            ]);
        });

        $ownerBSet = OwnerContext::withOwner($ownerB, static function () use ($ownerB): AttributeSet {
            return AttributeSet::create([
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
                'name' => 'Owner B Default',
                'code' => 'owner-b-default-' . Str::uuid(),
                'is_default' => true,
            ]);
        });

        OwnerContext::withOwner($ownerA, fn () => $ownerASet2->setAsDefault());

        expect($ownerASet1->refresh()->is_default)->toBeFalse()
            ->and($ownerASet2->refresh()->is_default)->toBeTrue()
            ->and($ownerBSet->refresh()->is_default)->toBeTrue();
    });

    it('scopes setAsDefault() updates to global sets when owner is null', function (): void {
        $owner = TestOwner::query()->create(['name' => 'Owned Owner']);

        $globalSet1 = OwnerContext::withOwner(null, static function (): AttributeSet {
            return AttributeSet::create([
                'name' => 'Global Default',
                'code' => 'global-default-' . Str::uuid(),
                'is_default' => true,
            ]);
        });

        $globalSet2 = OwnerContext::withOwner(null, static function (): AttributeSet {
            return AttributeSet::create([
                'name' => 'Global New Default',
                'code' => 'global-new-default-' . Str::uuid(),
                'is_default' => false,
            ]);
        });

        $ownedSet = OwnerContext::withOwner($owner, static function () use ($owner): AttributeSet {
            return AttributeSet::create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'name' => 'Owned Default',
                'code' => 'owned-default-' . Str::uuid(),
                'is_default' => true,
            ]);
        });

        OwnerContext::withOwner(null, fn () => $globalSet2->setAsDefault());

        expect($globalSet1->refresh()->is_default)->toBeFalse()
            ->and($globalSet2->refresh()->is_default)->toBeTrue()
            ->and($ownedSet->refresh()->is_default)->toBeTrue();
    });
});
