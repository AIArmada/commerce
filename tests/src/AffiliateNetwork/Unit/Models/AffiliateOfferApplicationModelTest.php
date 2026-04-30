<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;

describe('AffiliateOfferApplication Model', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->forSite($this->site)->create();
        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    describe('basic operations', function (): void {
        test('can create application', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($application->id)->not->toBeEmpty();
            expect($application->offer_id)->toBe($this->offer->id);
            expect($application->affiliate_id)->toBe($this->affiliate->id);
        });

        test('uses uuid primary key', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($application->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

        test('table name comes from config', function (): void {
            $application = new AffiliateOfferApplication;

            expect($application->getTable())->toBe('affiliate_network_offer_applications');
        });
    });

    describe('status helpers', function (): void {
        test('isPending returns true for pending status', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->pending()
                ->create();

            expect($application->isPending())->toBeTrue();
        });

        test('isPending returns false for approved status', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->approved()
                ->create();

            expect($application->isPending())->toBeFalse();
        });

        test('isApproved returns true for approved status', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->approved()
                ->create();

            expect($application->isApproved())->toBeTrue();
        });

        test('isApproved returns false for rejected status', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->rejected()
                ->create();

            expect($application->isApproved())->toBeFalse();
        });
    });

    describe('relationships', function (): void {
        test('belongs to offer', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($application->offer)->toBeInstanceOf(AffiliateOffer::class);
            expect($application->offer->id)->toBe($this->offer->id);
        });

        test('belongs to affiliate', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($application->affiliate)->toBeInstanceOf(Affiliate::class);
            expect($application->affiliate->id)->toBe($this->affiliate->id);
        });
    });

    describe('casts', function (): void {
        test('reviewed_at is immutable datetime', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->approved()
                ->create();

            expect($application->reviewed_at)->toBeInstanceOf(CarbonImmutable::class);
        });

        test('metadata is array', function (): void {
            $application = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'metadata' => ['source' => 'dashboard'],
                ]);

            expect($application->metadata)->toBe(['source' => 'dashboard']);
        });
    });

    describe('owner scoping via affiliate', function (): void {
        test('explicit global context only returns applications linked to global affiliates', function (): void {
            config([
                'affiliate-network.owner.enabled' => true,
                'affiliates.owner.enabled' => true,
            ]);

            $owner = User::factory()->create();

            $site = OwnerContext::withOwner($owner, fn () => AffiliateSite::factory()->verified()->forOwner($owner)->create());
            $offer = OwnerContext::withOwner($owner, fn () => AffiliateOffer::factory()->forSite($site)->create());
            $affiliate = OwnerContext::withOwner($owner, fn () => Affiliate::create([
                'code' => 'AFF' . uniqid(),
                'name' => 'Owned Affiliate',
                'status' => 'active',
                'commission_type' => 'percentage',
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]));

            $globalAffiliate = OwnerContext::withOwner(null, fn () => Affiliate::create([
                'code' => 'AFF' . uniqid(),
                'name' => 'Global Affiliate',
                'status' => 'active',
                'commission_type' => 'percentage',
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]));

            OwnerContext::withOwner($owner, fn () => AffiliateOfferApplication::factory()
                ->forOffer($offer)
                ->forAffiliate($affiliate)
                ->pending()
                ->create());

            $globalApplication = OwnerContext::withOwner(null, fn () => AffiliateOfferApplication::factory()
                ->forOffer($offer)
                ->forAffiliate($globalAffiliate)
                ->pending()
                ->create());

            $globalApplicationIds = OwnerContext::withOwner(null, fn () => AffiliateOfferApplication::query()->pluck('id')->all());

            expect($globalApplicationIds)
                ->toHaveCount(1)
                ->toContain($globalApplication->id);
        });
    });
});
