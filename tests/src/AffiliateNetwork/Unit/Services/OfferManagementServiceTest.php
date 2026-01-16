<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;

function createTestAffiliate(array $attributes = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'AFF' . uniqid(),
        'name' => 'Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ], $attributes));
}

describe('OfferManagementService', function (): void {
    beforeEach(function (): void {
        $this->service = app(OfferManagementService::class);
        $this->site = AffiliateSite::factory()->verified()->create();
    });

    describe('createOffer', function (): void {
        test('creates offer with required data', function (): void {
            $offer = $this->service->createOffer($this->site, [
                'name' => 'Test Offer',
            ]);

            expect($offer)->toBeInstanceOf(AffiliateOffer::class);
            expect($offer->name)->toBe('Test Offer');
            expect($offer->site_id)->toBe($this->site->id);
            expect($offer->slug)->toBe('test-offer');
        });

        test('creates offer with custom slug', function (): void {
            $offer = $this->service->createOffer($this->site, [
                'name' => 'Test Offer',
                'slug' => 'custom-slug',
            ]);

            expect($offer->slug)->toBe('custom-slug');
        });

        test('creates offer with pending status when approval required', function (): void {
            config(['affiliate-network.offers.require_approval' => true]);

            $offer = $this->service->createOffer($this->site, [
                'name' => 'Test Offer',
            ]);

            expect($offer->status)->toBe(AffiliateOffer::STATUS_PENDING);
        });

        test('creates offer with active status when approval not required', function (): void {
            config(['affiliate-network.offers.require_approval' => false]);

            $offer = $this->service->createOffer($this->site, [
                'name' => 'Test Offer',
            ]);

            expect($offer->status)->toBe(AffiliateOffer::STATUS_ACTIVE);
        });

        test('creates offer with explicit status', function (): void {
            $offer = $this->service->createOffer($this->site, [
                'name' => 'Test Offer',
                'status' => AffiliateOffer::STATUS_DRAFT,
            ]);

            expect($offer->status)->toBe(AffiliateOffer::STATUS_DRAFT);
        });

        test('creates offer with all optional fields', function (): void {
            $offer = $this->service->createOffer($this->site, [
                'name' => 'Full Offer',
                'description' => 'A detailed description',
                'terms' => 'Terms and conditions',
                'commission_type' => 'percentage',
                'commission_rate' => 1500,
                'currency' => 'USD',
                'cookie_days' => 60,
                'is_featured' => true,
                'landing_url' => 'https://example.com/landing',
            ]);

            expect($offer->description)->toBe('A detailed description');
            expect($offer->terms)->toBe('Terms and conditions');
            expect($offer->commission_type)->toBe('percentage');
            expect($offer->commission_rate)->toBe(1500);
            expect($offer->currency)->toBe('USD');
            expect($offer->cookie_days)->toBe(60);
            expect($offer->is_featured)->toBeTrue();
            expect($offer->landing_url)->toBe('https://example.com/landing');
        });
    });

    describe('applyForOffer', function (): void {
        beforeEach(function (): void {
            $this->offer = AffiliateOffer::factory()->active()->forSite($this->site)->create();
            $this->affiliate = createTestAffiliate();
        });

        test('creates pending application for offer requiring approval', function (): void {
            $this->offer->update(['requires_approval' => true]);

            $application = $this->service->applyForOffer($this->offer, $this->affiliate);

            expect($application)->toBeInstanceOf(AffiliateOfferApplication::class);
            expect($application->offer_id)->toBe($this->offer->id);
            expect($application->affiliate_id)->toBe($this->affiliate->id);
            expect($application->status)->toBe(AffiliateOfferApplication::STATUS_PENDING);
        });

        test('creates approved application for offer not requiring approval', function (): void {
            $this->offer->update(['requires_approval' => false]);

            $application = $this->service->applyForOffer($this->offer, $this->affiliate);

            expect($application->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
            expect($application->reviewed_at)->not->toBeNull();
        });

        test('creates approved application when auto_approve enabled', function (): void {
            config(['affiliate-network.applications.auto_approve' => true]);

            $application = $this->service->applyForOffer($this->offer, $this->affiliate);

            expect($application->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
        });

        test('includes reason in application', function (): void {
            $application = $this->service->applyForOffer(
                $this->offer,
                $this->affiliate,
                'I have a large audience'
            );

            expect($application->reason)->toBe('I have a large audience');
        });

        test('returns existing application if already exists', function (): void {
            $existing = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->pending()
                ->create();

            $application = $this->service->applyForOffer($this->offer, $this->affiliate);

            expect($application->id)->toBe($existing->id);
        });

        test('allows reapplication after cooldown period', function (): void {
            config(['affiliate-network.applications.cooldown_days' => 7]);

            $existing = AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->rejected()
                ->create([
                    'updated_at' => now()->subDays(10),
                ]);

            $application = $this->service->applyForOffer($this->offer, $this->affiliate);

            expect($application->id)->toBe($existing->id);
            expect($application->status)->toBe(AffiliateOfferApplication::STATUS_PENDING);
            expect($application->rejection_reason)->toBeNull();
        });

        test('throws exception when reapplying before cooldown', function (): void {
            config(['affiliate-network.applications.cooldown_days' => 7]);

            AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->rejected()
                ->create([
                    'updated_at' => now()->subDays(3),
                ]);

            $this->service->applyForOffer($this->offer, $this->affiliate);
        })->throws(RuntimeException::class, 'Cannot reapply');
    });

    describe('approveApplication', function (): void {
        test('approves pending application', function (): void {
            $application = AffiliateOfferApplication::factory()->pending()->create();

            $approved = $this->service->approveApplication($application, 'admin@example.com');

            expect($approved->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
            expect($approved->reviewed_by)->toBe('admin@example.com');
            expect($approved->reviewed_at)->not->toBeNull();
        });

        test('approves without reviewer', function (): void {
            $application = AffiliateOfferApplication::factory()->pending()->create();

            $approved = $this->service->approveApplication($application);

            expect($approved->status)->toBe(AffiliateOfferApplication::STATUS_APPROVED);
            expect($approved->reviewed_by)->toBeNull();
        });
    });

    describe('rejectApplication', function (): void {
        test('rejects application with reason', function (): void {
            $application = AffiliateOfferApplication::factory()->pending()->create();

            $rejected = $this->service->rejectApplication(
                $application,
                'Does not meet requirements',
                'admin@example.com'
            );

            expect($rejected->status)->toBe(AffiliateOfferApplication::STATUS_REJECTED);
            expect($rejected->rejection_reason)->toBe('Does not meet requirements');
            expect($rejected->reviewed_by)->toBe('admin@example.com');
            expect($rejected->reviewed_at)->not->toBeNull();
        });
    });

    describe('revokeApplication', function (): void {
        test('revokes approved application', function (): void {
            $application = AffiliateOfferApplication::factory()->approved()->create();

            $revoked = $this->service->revokeApplication(
                $application,
                'Violated terms',
                'admin@example.com'
            );

            expect($revoked->status)->toBe(AffiliateOfferApplication::STATUS_REVOKED);
            expect($revoked->rejection_reason)->toBe('Violated terms');
        });
    });

    describe('isApprovedForOffer', function (): void {
        test('returns true when approved', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            AffiliateOfferApplication::factory()
                ->forOffer($offer)
                ->forAffiliate($affiliate)
                ->approved()
                ->create();

            $result = $this->service->isApprovedForOffer($offer, $affiliate);

            expect($result)->toBeTrue();
        });

        test('returns false when pending', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            AffiliateOfferApplication::factory()
                ->forOffer($offer)
                ->forAffiliate($affiliate)
                ->pending()
                ->create();

            $result = $this->service->isApprovedForOffer($offer, $affiliate);

            expect($result)->toBeFalse();
        });

        test('returns false when no application', function (): void {
            $offer = AffiliateOffer::factory()->active()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            $result = $this->service->isApprovedForOffer($offer, $affiliate);

            expect($result)->toBeFalse();
        });
    });

    describe('getApprovedOffers', function (): void {
        test('returns only active approved offers', function (): void {
            $affiliate = createTestAffiliate();

            $activeOffer = AffiliateOffer::factory()->active()->forSite($this->site)->create();
            $pausedOffer = AffiliateOffer::factory()->paused()->forSite($this->site)->create();
            $notApplied = AffiliateOffer::factory()->active()->forSite($this->site)->create();

            AffiliateOfferApplication::factory()
                ->forOffer($activeOffer)
                ->forAffiliate($affiliate)
                ->approved()
                ->create();

            AffiliateOfferApplication::factory()
                ->forOffer($pausedOffer)
                ->forAffiliate($affiliate)
                ->approved()
                ->create();

            $offers = $this->service->getApprovedOffers($affiliate);

            expect($offers)->toHaveCount(1);
            expect($offers->first()->id)->toBe($activeOffer->id);
        });
    });
});
