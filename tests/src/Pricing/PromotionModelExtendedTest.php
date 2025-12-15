<?php

declare(strict_types=1);

use AIArmada\Pricing\Enums\PromotionType;
use AIArmada\Pricing\Models\Promotion;
use Illuminate\Support\Carbon;

describe('Promotion Model - Extended Tests', function (): void {
    describe('getTable', function (): void {
        it('returns configured table name', function (): void {
            $promotion = new Promotion;

            expect($promotion->getTable())->toBe(config('pricing.tables.promotions', 'promotions'));
        });
    });

    describe('isActive', function (): void {
        it('returns false when is_active is false', function (): void {
            $promotion = Promotion::create([
                'name' => 'Inactive Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => false,
            ]);

            expect($promotion->isActive())->toBeFalse();
        });

        it('returns true when is_active is true and no restrictions', function (): void {
            $promotion = Promotion::create([
                'name' => 'Active Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
            ]);

            expect($promotion->isActive())->toBeTrue();
        });

        it('returns false when before start date', function (): void {
            $promotion = Promotion::create([
                'name' => 'Future Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
                'starts_at' => Carbon::now()->addWeek(),
            ]);

            expect($promotion->isActive())->toBeFalse();
        });

        it('returns false when after end date', function (): void {
            $promotion = Promotion::create([
                'name' => 'Expired Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
                'starts_at' => Carbon::now()->subWeek(),
                'ends_at' => Carbon::now()->subDay(),
            ]);

            expect($promotion->isActive())->toBeFalse();
        });

        it('returns true when within date range', function (): void {
            $promotion = Promotion::create([
                'name' => 'Current Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addDay(),
            ]);

            expect($promotion->isActive())->toBeTrue();
        });

        it('returns false when usage limit reached', function (): void {
            $promotion = Promotion::create([
                'name' => 'Limited Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
                'usage_limit' => 10,
                'usage_count' => 10,
            ]);

            expect($promotion->isActive())->toBeFalse();
        });

        it('returns true when usage limit not reached', function (): void {
            $promotion = Promotion::create([
                'name' => 'Available Promo',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
                'usage_limit' => 10,
                'usage_count' => 5,
            ]);

            expect($promotion->isActive())->toBeTrue();
        });
    });

    describe('calculateDiscount', function (): void {
        it('calculates percentage discount correctly', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Percentage,
                'discount_value' => 20, // 20%
            ]);

            expect($promotion->calculateDiscount(10000))->toBe(2000);
            expect($promotion->calculateDiscount(5000))->toBe(1000);
            expect($promotion->calculateDiscount(1))->toBe(0); // rounds to 0
        });

        it('calculates fixed discount correctly', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Fixed,
                'discount_value' => 1500, // RM15
            ]);

            expect($promotion->calculateDiscount(10000))->toBe(1500);
            expect($promotion->calculateDiscount(20000))->toBe(1500);
        });

        it('caps fixed discount at item price', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Fixed,
                'discount_value' => 5000, // RM50
            ]);

            expect($promotion->calculateDiscount(3000))->toBe(3000); // Only RM30 available
        });

        it('returns 0 for BuyXGetY type', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::BuyXGetY,
                'discount_value' => 1,
            ]);

            expect($promotion->calculateDiscount(10000))->toBe(0);
        });
    });

    describe('incrementUsage', function (): void {
        it('increments usage count', function (): void {
            $promotion = Promotion::create([
                'name' => 'Usage Test',
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'usage_count' => 0,
            ]);

            $promotion->incrementUsage();

            expect($promotion->fresh()->usage_count)->toBe(1);
        });

        it('returns self for chaining', function (): void {
            $promotion = Promotion::create([
                'name' => 'Chain Test',
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
            ]);

            $result = $promotion->incrementUsage();

            expect($result)->toBe($promotion);
        });
    });

    describe('hasRemainingUsage', function (): void {
        it('returns true when no usage limit', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'usage_limit' => null,
                'usage_count' => 1000,
            ]);

            expect($promotion->hasRemainingUsage())->toBeTrue();
        });

        it('returns true when under limit', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'usage_limit' => 100,
                'usage_count' => 50,
            ]);

            expect($promotion->hasRemainingUsage())->toBeTrue();
        });

        it('returns false when at limit', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'usage_limit' => 100,
                'usage_count' => 100,
            ]);

            expect($promotion->hasRemainingUsage())->toBeFalse();
        });

        it('returns false when over limit', function (): void {
            $promotion = new Promotion([
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'usage_limit' => 100,
                'usage_count' => 101,
            ]);

            expect($promotion->hasRemainingUsage())->toBeFalse();
        });
    });

    describe('scopes', function (): void {
        it('filters active promotions', function (): void {
            $prefix = uniqid();

            Promotion::create([
                'name' => "Active-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
            ]);

            Promotion::create([
                'name' => "Inactive-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => false,
            ]);

            Promotion::create([
                'name' => "Future-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'starts_at' => Carbon::now()->addWeek(),
            ]);

            Promotion::create([
                'name' => "Expired-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'ends_at' => Carbon::now()->subDay(),
            ]);

            Promotion::create([
                'name' => "LimitReached-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'usage_limit' => 10,
                'usage_count' => 10,
            ]);

            $active = Promotion::where('name', 'like', "%-{$prefix}")->active()->get();

            expect($active)->toHaveCount(1)
                ->and($active->first()->name)->toBe("Active-{$prefix}");
        });

        // Note: forOwner tests require owner_type/owner_id columns in test schema
        // which aren't present. Full owner testing done in integration tests.
    });

    describe('relationships', function (): void {
        it('has morphToMany products relationship', function (): void {
            $promotion = new Promotion;

            expect($promotion->products())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphToMany::class);
        });

        it('has morphToMany categories relationship', function (): void {
            $promotion = new Promotion;

            expect($promotion->categories())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphToMany::class);
        });
    });

    describe('soft deletes', function (): void {
        it('soft deletes promotion', function (): void {
            $promotion = Promotion::create([
                'name' => 'Soft Delete Test',
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
            ]);

            $id = $promotion->id;
            $promotion->delete();

            expect(Promotion::find($id))->toBeNull()
                ->and(Promotion::withTrashed()->find($id))->not->toBeNull();
        });
    });

    describe('default attributes', function (): void {
        it('has default type of percentage', function (): void {
            $promotion = new Promotion;

            // Type is cast to enum, so check against enum
            expect($promotion->type)->toBe(PromotionType::Percentage);
        });

        it('has default priority of 0', function (): void {
            $promotion = new Promotion;

            expect($promotion->priority)->toBe(0);
        });

        it('has default is_stackable of false', function (): void {
            $promotion = new Promotion;

            expect($promotion->is_stackable)->toBeFalse();
        });

        it('has default is_active of true', function (): void {
            $promotion = new Promotion;

            expect($promotion->is_active)->toBeTrue();
        });

        it('has default usage_count of 0', function (): void {
            $promotion = new Promotion;

            expect($promotion->usage_count)->toBe(0);
        });
    });

    describe('casts', function (): void {
        it('casts type to PromotionType enum', function (): void {
            $promotion = Promotion::create([
                'name' => 'Cast Test',
                'type' => 'percentage',
                'discount_value' => 10,
                'is_active' => true,
            ]);

            expect($promotion->type)->toBe(PromotionType::Percentage);
        });

        it('casts conditions to array', function (): void {
            $conditions = ['min_quantity' => 5, 'categories' => ['electronics']];

            $promotion = Promotion::create([
                'name' => 'Conditions Test',
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'conditions' => $conditions,
            ]);

            expect($promotion->conditions)->toBeArray()
                ->and($promotion->conditions['min_quantity'])->toBe(5);
        });

        it('casts dates correctly', function (): void {
            $promotion = Promotion::create([
                'name' => 'Date Cast Test',
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'starts_at' => '2024-01-01 00:00:00',
                'ends_at' => '2024-12-31 23:59:59',
            ]);

            expect($promotion->starts_at)->toBeInstanceOf(Carbon::class)
                ->and($promotion->ends_at)->toBeInstanceOf(Carbon::class);
        });
    });

    describe('forOwner scope', function (): void {
        it('returns all records when owner feature is disabled', function (): void {
            config(['pricing.owner.enabled' => false]);

            $prefix = uniqid();

            Promotion::create([
                'name' => "Test1-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
            ]);

            Promotion::create([
                'name' => "Test2-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
            ]);

            $promotions = Promotion::where('name', 'like', "Test%-{$prefix}")->forOwner(null)->get();

            expect($promotions)->toHaveCount(2);
        });

        it('returns global records when no owner provided and feature enabled', function (): void {
            config(['pricing.owner.enabled' => true]);

            $prefix = uniqid();

            // Global record (no owner)
            Promotion::create([
                'name' => "Global-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            // Owned record
            Promotion::create([
                'name' => "Owned-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => 'App\\Models\\Store',
                'owner_id' => 'store-123',
            ]);

            $promotions = Promotion::where('name', 'like', "%-{$prefix}")->forOwner(null)->get();

            expect($promotions)->toHaveCount(1)
                ->and($promotions->first()->name)->toBe("Global-{$prefix}");
        });

        it('returns owned and global records when owner provided with includeGlobal true', function (): void {
            config(['pricing.owner.enabled' => true]);

            $prefix = uniqid();
            $ownerId = 'owner-' . uniqid();

            // Create a mock owner model
            $owner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $owner->id = $ownerId;
            $owner->setTable('stores');

            // Global record
            Promotion::create([
                'name' => "Global-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            // Owned record matching owner
            Promotion::create([
                'name' => "Owned-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $ownerId,
            ]);

            // Different owner
            Promotion::create([
                'name' => "Other-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => 'App\\Models\\Store',
                'owner_id' => 'other-store-' . uniqid(),
            ]);

            $promotions = Promotion::where('name', 'like', "%-{$prefix}")->forOwner($owner, true)->get();

            expect($promotions)->toHaveCount(2);
        });

        it('returns only owned records when includeGlobal is false', function (): void {
            config(['pricing.owner.enabled' => true]);

            $prefix = uniqid();
            $ownerId = 'owner-' . uniqid();

            // Create a mock owner model
            $owner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $owner->id = $ownerId;
            $owner->setTable('stores');

            // Global record
            Promotion::create([
                'name' => "Global-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            // Owned record matching owner
            Promotion::create([
                'name' => "Owned-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $ownerId,
            ]);

            $promotions = Promotion::where('name', 'like', "%-{$prefix}")->forOwner($owner, false)->get();

            expect($promotions)->toHaveCount(1)
                ->and($promotions->first()->name)->toBe("Owned-{$prefix}");
        });

        it('returns only global records when no owner provided and includeGlobal false', function (): void {
            config(['pricing.owner.enabled' => true]);

            $prefix = uniqid();

            // Global record
            Promotion::create([
                'name' => "Global-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => null,
                'owner_id' => null,
            ]);

            // Owned record
            Promotion::create([
                'name' => "Owned-{$prefix}",
                'type' => PromotionType::Percentage,
                'discount_value' => 10,
                'is_active' => true,
                'owner_type' => 'App\\Models\\Store',
                'owner_id' => 'store-' . uniqid(),
            ]);

            $promotions = Promotion::where('name', 'like', "%-{$prefix}")->forOwner(null, false)->get();

            expect($promotions)->toHaveCount(1)
                ->and($promotions->first()->name)->toBe("Global-{$prefix}");
        });
    });
});
