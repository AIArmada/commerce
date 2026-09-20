<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Services\ProgramService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

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

            expect($offer->status)->toBe(OfferStatus::Draft);
        });

        test('creates offer with explicit status', function (): void {
            $offer = $this->service->createOffer($this->site, [
                'name' => 'Test Offer',
                'status' => OfferStatus::Draft,
            ]);

            expect($offer->status)->toBe(OfferStatus::Draft);
        });

        test('creates offer with all optional fields', function (): void {
            $offer = $this->service->createOffer($this->site, [
                'name' => 'Full Offer',
                'description' => 'A detailed description',
                'terms' => 'Terms and conditions',
                'rate_base_bp' => 1500,
                'volume_tiers' => [['min_volume_minor' => 100000, 'rate_bp' => 1800]],
                'active_promotions' => [['id' => 'promo-1', 'name' => 'Spring', 'ends_at' => null]],
                'currency' => 'USD',
                'cookie_days' => 60,
                'is_featured' => true,
                'landing_url' => 'https://example.com/landing',
            ]);

            expect($offer->description)->toBe('A detailed description');
            expect($offer->terms)->toBe('Terms and conditions');
            expect($offer->rate_base_bp)->toBe(1500);
            expect($offer->volume_tiers)->toBe([['min_volume_minor' => 100000, 'rate_bp' => 1800]]);
            expect($offer->active_promotions)->toBe([['id' => 'promo-1', 'name' => 'Spring', 'ends_at' => null]]);
            expect($offer->currency)->toBe('USD');
            expect($offer->cookie_days)->toBe(60);
            expect($offer->is_featured)->toBeTrue();
            expect($offer->landing_url)->toBe('https://example.com/landing');
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

            expect($rejected->status)->toBe(ApplicationStatus::Rejected);
            expect($rejected->rejection_reason)->toBe('Does not meet requirements');
            expect($rejected->reviewed_by)->toBe('admin@example.com');
            expect($rejected->reviewed_at)->not->toBeNull();
        });

        test('fails when application is no longer accessible', function (): void {
            $application = AffiliateOfferApplication::factory()->pending()->create();

            $application->delete();

            $this->service->rejectApplication($application, 'Does not meet requirements', 'admin@example.com');
        })->throws(ModelNotFoundException::class);
    });

    describe('revokeApplication', function (): void {
        test('revokes approved application', function (): void {
            $application = AffiliateOfferApplication::factory()->approved()->create();

            $revoked = $this->service->revokeApplication(
                $application,
                'Violated terms',
                'admin@example.com'
            );

            expect($revoked->status)->toBe(ApplicationStatus::Revoked);
            expect($revoked->rejection_reason)->toBe('Violated terms');
        });

        test('fails when application is no longer accessible', function (): void {
            $application = AffiliateOfferApplication::factory()->approved()->create();

            $application->delete();

            $this->service->revokeApplication($application, 'Violated terms', 'admin@example.com');
        })->throws(ModelNotFoundException::class);
    });

    describe('isApprovedForOffer', function (): void {
        test('returns true when approved', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
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
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
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
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            $result = $this->service->isApprovedForOffer($offer, $affiliate);

            expect($result)->toBeFalse();
        });
    });

    describe('getApprovedOffers', function (): void {
        test('returns only active approved offers', function (): void {
            $affiliate = createTestAffiliate();

            $activeOffer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $pausedOffer = AffiliateOffer::factory()->archived()->forSite($this->site)->create();
            $notApplied = AffiliateOffer::factory()->published()->forSite($this->site)->create();

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

        test('respects the limit', function (): void {
            $affiliate = createTestAffiliate();

            foreach (range(1, 3) as $i) {
                $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();

                AffiliateOfferApplication::factory()
                    ->forOffer($offer)
                    ->forAffiliate($affiliate)
                    ->approved()
                    ->create();
            }

            expect($this->service->getApprovedOffers($affiliate, 2))->toHaveCount(2)
                ->and($this->service->getApprovedOffers($affiliate))->toHaveCount(3);
        });

        test('includes published local offers with approved core memberships', function (): void {
            $affiliate = createTestAffiliate();
            $program = AffiliateProgram::create([
                'name' => 'Linked Program',
                'slug' => 'linked-program-' . uniqid(),
                'status' => ProgramStatus::Active,
                'visibility' => ProgramVisibility::Public,
                'requires_approval' => false,
                'commission_type' => CommissionType::Percentage,
            ]);

            app(ProgramService::class)->joinProgram($affiliate, $program);

            $linked = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => $program->getKey(),
            ]);

            $offers = $this->service->getApprovedOffers($affiliate);

            expect($offers->pluck('id')->all())->toContain((string) $linked->getKey());
        });

        test('excludes local offers when the core membership is pending', function (): void {
            $affiliate = createTestAffiliate();
            $program = AffiliateProgram::create([
                'name' => 'Approval Program',
                'slug' => 'approval-program-' . uniqid(),
                'status' => ProgramStatus::Active,
                'visibility' => ProgramVisibility::Public,
                'requires_approval' => true,
                'commission_type' => CommissionType::Percentage,
            ]);

            app(ProgramService::class)->joinProgram($affiliate, $program);

            AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => $program->getKey(),
            ]);

            expect($this->service->getApprovedOffers($affiliate))->toHaveCount(0);
        });

        test('falls back to network applications when the linked program is gone', function (): void {
            $affiliate = createTestAffiliate();

            $orphaned = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => (string) Str::uuid(),
            ]);

            AffiliateOfferApplication::factory()
                ->forOffer($orphaned)
                ->forAffiliate($affiliate)
                ->approved()
                ->create();

            $offers = $this->service->getApprovedOffers($affiliate);

            expect($offers->pluck('id')->all())->toContain((string) $orphaned->getKey());
        });

        test('excludes remote mirrors without approved applications', function (): void {
            $affiliate = createTestAffiliate();
            $program = AffiliateProgram::create([
                'name' => 'Remote Program',
                'slug' => 'remote-program-' . uniqid(),
                'status' => ProgramStatus::Active,
                'visibility' => ProgramVisibility::Public,
                'requires_approval' => false,
                'commission_type' => CommissionType::Percentage,
            ]);

            app(ProgramService::class)->joinProgram($affiliate, $program);

            AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => $program->getKey(),
                'metadata' => ['catalog_source' => 'remote'],
            ]);

            expect($this->service->getApprovedOffers($affiliate))->toHaveCount(0);
        });
    });

    describe('resolvePublicOfferOrFail', function (): void {
        test('returns active public offer', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'visibility' => OfferVisibility::Public,
            ]);

            $resolved = $this->service->resolvePublicOfferOrFail($offer->id);

            expect($resolved->id)->toBe($offer->id);
        });

        test('fails for non public offer', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'visibility' => OfferVisibility::Private,
            ]);

            $this->service->resolvePublicOfferOrFail($offer->id);
        })->throws(ModelNotFoundException::class);

        test('fails for non active offer', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create([
                'status' => OfferStatus::Draft,
                'visibility' => OfferVisibility::Public,
            ]);

            $this->service->resolvePublicOfferOrFail($offer->id);
        })->throws(ModelNotFoundException::class);
    });

    describe('applicationStatusForOffer', function (): void {
        test('returns the status string for an existing network application', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $offer->update(['requires_approval' => false]);
            $affiliate = createTestAffiliate();

            $this->service->applyForOffer($offer, $affiliate);

            $status = $this->service->applicationStatusForOffer($offer, $affiliate);

            expect($status)->toBeString();
            expect($status)->toBe(ApplicationStatus::Approved->value);
        });

        test('returns null when no application exists', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            expect($this->service->applicationStatusForOffer($offer, $affiliate))->toBeNull();
        });
    });
});
