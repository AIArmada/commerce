<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\QueryException;

describe('AffiliateOfferCategory Model', function (): void {
    describe('basic operations', function (): void {
        test('can create category', function (): void {
            $category = AffiliateOfferCategory::factory()->create([
                'name' => 'Electronics',
            ]);

            expect($category->id)->not->toBeEmpty();
            expect($category->name)->toBe('Electronics');
        });

        test('uses uuid primary key', function (): void {
            $category = AffiliateOfferCategory::factory()->create();

            expect($category->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

    });

    describe('relationships', function (): void {
        test('belongs to parent category', function (): void {
            $parent = AffiliateOfferCategory::factory()->create();
            $child = AffiliateOfferCategory::factory()->forParent($parent)->create();

            expect($child->parent)->toBeInstanceOf(AffiliateOfferCategory::class);
            expect($child->parent->id)->toBe($parent->id);
        });

        test('has morphable owner', function (): void {
            $user = User::factory()->create();
            $category = AffiliateOfferCategory::factory()->forOwner($user)->create();

            expect($category->owner)->toBeInstanceOf(User::class);
            expect($category->owner->id)->toBe($user->id);
        });
    });

    describe('cascading deletes', function (): void {
        test('deleting category re-parents children', function (): void {
            $grandparent = AffiliateOfferCategory::factory()->create();
            $parent = AffiliateOfferCategory::factory()->forParent($grandparent)->create();
            $child = AffiliateOfferCategory::factory()->forParent($parent)->create();

            $parent->delete();

            expect($child->fresh()->parent_id)->toBe($grandparent->id);
        });

        test('deleting category nullifies offer category_id', function (): void {
            $category = AffiliateOfferCategory::factory()->create();
            $offer = AffiliateOffer::factory()->forCategory($category)->create();

            $category->delete();

            expect($offer->fresh()->category_id)->toBeNull();
        });
    });

    describe('casts', function (): void {
        test('sort_order is integer', function (): void {
            $category = AffiliateOfferCategory::factory()->sortOrder(5)->create();

            expect($category->sort_order)->toBe(5);
            expect($category->sort_order)->toBeInt();
        });

        test('is_active is boolean', function (): void {
            $category = AffiliateOfferCategory::factory()->active()->create();

            expect($category->is_active)->toBeTrue();
            expect($category->is_active)->toBeBool();
        });
    });

    describe('owner scoping', function (): void {
        test('forOwner scope filters by owner when enabled', function (): void {
            config(['affiliate-network.owner.enabled' => true]);

            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $cat1 = OwnerContext::withOwner($user1, fn () => AffiliateOfferCategory::factory()->forOwner($user1)->create());
            OwnerContext::withOwner($user2, fn () => AffiliateOfferCategory::factory()->forOwner($user2)->create());

            $results = AffiliateOfferCategory::forOwner($user1)->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($cat1->id);
        });
    });

    describe('owner slug uniqueness', function (): void {
        test('allows the same slug for different owners', function (): void {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $cat1 = AffiliateOfferCategory::factory()->forOwner($user1)->create(['slug' => 'shared-slug']);
            $cat2 = AffiliateOfferCategory::factory()->forOwner($user2)->create(['slug' => 'shared-slug']);

            expect($cat1->slug)->toBe('shared-slug');
            expect($cat2->slug)->toBe('shared-slug');
        });

        test('rejects duplicate slugs for the same owner', function (): void {
            $user = User::factory()->create();

            AffiliateOfferCategory::factory()->forOwner($user)->create(['slug' => 'dup-slug']);
            AffiliateOfferCategory::factory()->forOwner($user)->create(['slug' => 'dup-slug']);
        })->throws(QueryException::class);
    });
});
