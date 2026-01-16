<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

describe('AffiliateOffer Model', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create();
    });

    describe('basic operations', function (): void {
        test('can create offer', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create([
                'name' => 'Summer Sale',
            ]);

            expect($offer->id)->not->toBeEmpty();
            expect($offer->name)->toBe('Summer Sale');
            expect($offer->site_id)->toBe($this->site->id);
        });

        test('uses uuid primary key', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();

            expect($offer->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

        test('table name comes from config', function (): void {
            $offer = new AffiliateOffer;

            expect($offer->getTable())->toBe('affiliate_network_offers');
        });
    });

    describe('isActive', function (): void {
        test('returns true for active status', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create();

            expect($offer->isActive())->toBeTrue();
        });

        test('returns false for non-active status', function (): void {
            $offer = AffiliateOffer::factory()->paused()->forSite($this->site)->create();

            expect($offer->isActive())->toBeFalse();
        });

        test('returns false when starts_at is in future', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
                'starts_at' => now()->addDays(1),
            ]);

            expect($offer->isActive())->toBeFalse();
        });

        test('returns false when ends_at is in past', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
                'ends_at' => now()->subDays(1),
            ]);

            expect($offer->isActive())->toBeFalse();
        });

        test('returns true within date range', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
                'starts_at' => now()->subDays(1),
                'ends_at' => now()->addDays(1),
            ]);

            expect($offer->isActive())->toBeTrue();
        });
    });

    describe('relationships', function (): void {
        test('belongs to site', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();

            expect($offer->site)->toBeInstanceOf(AffiliateSite::class);
            expect($offer->site->id)->toBe($this->site->id);
        });

        test('belongs to category', function (): void {
            $category = AffiliateOfferCategory::factory()->create();
            $offer = AffiliateOffer::factory()->forSite($this->site)->forCategory($category)->create();

            expect($offer->category)->toBeInstanceOf(AffiliateOfferCategory::class);
            expect($offer->category->id)->toBe($category->id);
        });

        test('has many creatives', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();
            AffiliateOfferCreative::factory()->count(3)->forOffer($offer)->create();

            expect($offer->creatives)->toHaveCount(3);
        });

        test('has many applications', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();
            AffiliateOfferApplication::factory()->count(2)->forOffer($offer)->create();

            expect($offer->applications)->toHaveCount(2);
        });

        test('has many links', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();
            AffiliateOfferLink::factory()->count(5)->forOffer($offer)->create();

            expect($offer->links)->toHaveCount(5);
        });
    });

    describe('cascading deletes', function (): void {
        test('deleting offer deletes creatives', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();
            $creatives = AffiliateOfferCreative::factory()->count(3)->forOffer($offer)->create();

            $offer->delete();

            expect(AffiliateOfferCreative::whereIn('id', $creatives->pluck('id'))->count())->toBe(0);
        });

        test('deleting offer deletes applications', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();
            $applications = AffiliateOfferApplication::factory()->count(2)->forOffer($offer)->create();

            $offer->delete();

            expect(AffiliateOfferApplication::whereIn('id', $applications->pluck('id'))->count())->toBe(0);
        });

        test('deleting offer deletes links', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create();
            $links = AffiliateOfferLink::factory()->count(4)->forOffer($offer)->create();

            $offer->delete();

            expect(AffiliateOfferLink::whereIn('id', $links->pluck('id'))->count())->toBe(0);
        });
    });

    describe('casts', function (): void {
        test('commission_rate is integer', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create([
                'commission_rate' => 1500,
            ]);

            expect($offer->commission_rate)->toBe(1500);
            expect($offer->commission_rate)->toBeInt();
        });

        test('is_featured is boolean', function (): void {
            $offer = AffiliateOffer::factory()->featured()->forSite($this->site)->create();

            expect($offer->is_featured)->toBeTrue();
            expect($offer->is_featured)->toBeBool();
        });

        test('restrictions is array', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create([
                'restrictions' => ['countries' => ['US', 'CA']],
            ]);

            expect($offer->restrictions)->toBe(['countries' => ['US', 'CA']]);
        });

        test('starts_at and ends_at are immutable datetime', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create([
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ]);

            expect($offer->starts_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
            expect($offer->ends_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
        });
    });
});
