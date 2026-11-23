<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\ResolvePublicOfferQuery;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RejectApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Queries\ApplicationStatusQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

function approvedOffersFor(string $affiliateId, int $limit = 500): Collection
{
    $ids = app(ApplicationStatusQuery::class)->approvedOfferIds($affiliateId, $limit);

    return AffiliateOffer::query()
        ->whereIn('id', $ids)
        ->where('status', OfferStatus::Published)
        ->get();
}

describe('offer management actions', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create();
    });

    describe('createOffer', function (): void {
        test('creates offer with required data', function (): void {
            $offer = app(CreateOffer::class)->execute($this->site->toRecord(), [
                'name' => 'Test Offer',
            ]);

            expect($offer)->toBeInstanceOf(AffiliateOffer::class);
            expect($offer->name)->toBe('Test Offer');
            expect($offer->site_id)->toBe($this->site->id);
            expect($offer->slug)->toBe('test-offer');
        });

        test('creates offer with custom slug', function (): void {
            $offer = app(CreateOffer::class)->execute($this->site->toRecord(), [
                'name' => 'Test Offer',
                'slug' => 'custom-slug',
            ]);

            expect($offer->slug)->toBe('custom-slug');
        });

        test('creates offer as draft by default', function (): void {
            $offer = app(CreateOffer::class)->execute($this->site->toRecord(), [
                'name' => 'Test Offer',
            ]);

            expect($offer->status)->toBe(OfferStatus::Draft);
        });

        test('creates offer with explicit status', function (): void {
            $offer = app(CreateOffer::class)->execute($this->site->toRecord(), [
                'name' => 'Test Offer',
                'status' => OfferStatus::Draft,
            ]);

            expect($offer->status)->toBe(OfferStatus::Draft);
        });

        test('creates offer with all optional fields', function (): void {
            $offer = app(CreateOffer::class)->execute($this->site->toRecord(), [
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
            expect($offer->volume_tiers)->toBe([['min_volume_minor' => 100000, 'rate_bp' => 1800, 'currency' => 'USD']]);
            expect($offer->active_promotions)->toBe([['id' => 'promo-1', 'name' => 'Spring', 'ends_at' => null]]);
            expect($offer->currency)->toBe('USD');
            expect($offer->cookie_days)->toBe(60);
            expect($offer->is_featured)->toBeTrue();
            expect($offer->landing_url)->toBe('https://example.com/landing');
        });

        test('stamps volume tiers with the offer currency by default', function (): void {
            $offer = app(CreateOffer::class)->execute($this->site->toRecord(), [
                'name' => 'Tiered Offer',
                'currency' => 'myr',
                'volume_tiers' => [
                    ['min_volume_minor' => 100000, 'rate_bp' => 1800],
                    ['min_volume_minor' => 50000, 'rate_bp' => 1500, 'currency' => 'usd'],
                ],
            ]);

            expect($offer->volume_tiers)->toBe([
                ['min_volume_minor' => 100000, 'rate_bp' => 1800, 'currency' => 'MYR'],
                ['min_volume_minor' => 50000, 'rate_bp' => 1500, 'currency' => 'USD'],
            ]);
        });
    });

    describe('rejectApplication', function (): void {
        test('rejects application with reason', function (): void {
            $application = AffiliateOfferApplication::factory()->pending()->create();

            $rejected = app(RejectApplication::class)->execute(
                $application,
                'Does not meet requirements',
                'admin@example.com',
            );

            expect($rejected->status)->toBe(ApplicationStatus::Rejected);
            expect($rejected->rejection_reason)->toBe('Does not meet requirements');
            expect($rejected->reviewed_by)->toBe('admin@example.com');
            expect($rejected->reviewed_at)->not->toBeNull();
        });

        test('fails when application is no longer accessible', function (): void {
            $application = AffiliateOfferApplication::factory()->pending()->create();

            $application->delete();

            app(RejectApplication::class)->execute($application, 'Does not meet requirements', 'admin@example.com');
        })->throws(ModelNotFoundException::class);
    });

    describe('revokeApplication', function (): void {
        test('revokes approved application', function (): void {
            $application = AffiliateOfferApplication::factory()->approved()->create();

            $revoked = app(RevokeApplication::class)->execute(
                $application,
                'Violated terms',
                'admin@example.com',
            );

            expect($revoked->status)->toBe(ApplicationStatus::Revoked);
            expect($revoked->rejection_reason)->toBe('Violated terms');
        });

        test('fails when application is no longer accessible', function (): void {
            $application = AffiliateOfferApplication::factory()->approved()->create();

            $application->delete();

            app(RevokeApplication::class)->execute($application, 'Violated terms', 'admin@example.com');
        })->throws(ModelNotFoundException::class);
    });

    describe('application status', function (): void {
        test('returns true when approved', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            AffiliateOfferApplication::factory()
                ->forOffer($offer)
                ->forAffiliateId((string) $affiliate->getKey())
                ->approved()
                ->create();

            $result = app(ApplicationStatusQuery::class)->isApprovedForOffer($offer->id, (string) $affiliate->getKey());

            expect($result)->toBeTrue();
        });

        test('returns false when pending', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            AffiliateOfferApplication::factory()
                ->forOffer($offer)
                ->forAffiliateId((string) $affiliate->getKey())
                ->pending()
                ->create();

            $result = app(ApplicationStatusQuery::class)->isApprovedForOffer($offer->id, (string) $affiliate->getKey());

            expect($result)->toBeFalse();
        });

        test('returns false when no application', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            $result = app(ApplicationStatusQuery::class)->isApprovedForOffer($offer->id, (string) $affiliate->getKey());

            expect($result)->toBeFalse();
        });
    });

    describe('getApprovedOffers', function (): void {
        test('returns only active approved offers', function (): void {
            $affiliate = createTestAffiliate();

            $activeOffer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $pausedOffer = AffiliateOffer::factory()->archived()->forSite($this->site)->create();
            AffiliateOffer::factory()->published()->forSite($this->site)->create();

            AffiliateOfferApplication::factory()
                ->forOffer($activeOffer)
                ->forAffiliateId((string) $affiliate->getKey())
                ->approved()
                ->create();

            AffiliateOfferApplication::factory()
                ->forOffer($pausedOffer)
                ->forAffiliateId((string) $affiliate->getKey())
                ->approved()
                ->create();

            $offers = approvedOffersFor((string) $affiliate->getKey());

            expect($offers)->toHaveCount(1);
            expect($offers->first()->id)->toBe($activeOffer->id);
        });

        test('respects the limit', function (): void {
            $affiliate = createTestAffiliate();

            foreach (range(1, 3) as $i) {
                $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();

                AffiliateOfferApplication::factory()
                    ->forOffer($offer)
                    ->forAffiliateId((string) $affiliate->getKey())
                    ->approved()
                    ->create();
            }

            expect(approvedOffersFor((string) $affiliate->getKey(), 2))->toHaveCount(2)
                ->and(approvedOffersFor((string) $affiliate->getKey()))->toHaveCount(3);
        });

        test('includes published mirrored offers with approved network applications', function (): void {
            $affiliate = createTestAffiliate();

            $linked = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => (string) Str::uuid(),
            ]);

            AffiliateOfferApplication::factory()
                ->forOffer($linked)
                ->forAffiliateId((string) $affiliate->getKey())
                ->approved()
                ->create();

            $offers = approvedOffersFor((string) $affiliate->getKey());

            expect($offers->pluck('id')->all())->toContain((string) $linked->getKey());
        });

        test('excludes mirrored offers without approved applications', function (): void {
            $affiliate = createTestAffiliate();

            $linked = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => (string) Str::uuid(),
            ]);

            AffiliateOfferApplication::factory()
                ->forOffer($linked)
                ->forAffiliateId((string) $affiliate->getKey())
                ->pending()
                ->create();

            expect(approvedOffersFor((string) $affiliate->getKey()))->toHaveCount(0);
        });

        test('includes mirrored offers whose program vanished when the application is approved', function (): void {
            $affiliate = createTestAffiliate();

            $orphaned = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => (string) Str::uuid(),
            ]);

            AffiliateOfferApplication::factory()
                ->forOffer($orphaned)
                ->forAffiliateId((string) $affiliate->getKey())
                ->approved()
                ->create();

            $offers = approvedOffersFor((string) $affiliate->getKey());

            expect($offers->pluck('id')->all())->toContain((string) $orphaned->getKey());
        });

        test('excludes mirrored offers with no application at all', function (): void {
            $affiliate = createTestAffiliate();

            AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'external_program_id' => (string) Str::uuid(),
                'metadata' => ['catalog_source' => 'remote'],
            ]);

            expect(approvedOffersFor((string) $affiliate->getKey()))->toHaveCount(0);
        });
    });

    describe('resolvePublicOfferOrFail', function (): void {
        test('returns active public offer', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'visibility' => OfferVisibility::Public,
            ]);

            $resolved = app(ResolvePublicOfferQuery::class)->find($offer->id);

            expect($resolved?->id)->toBe($offer->id);
        });

        test('fails for non public offer', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
                'visibility' => OfferVisibility::Private,
            ]);

            expect(app(ResolvePublicOfferQuery::class)->find($offer->id))->toBeNull();
        });

        test('fails for non active offer', function (): void {
            $offer = AffiliateOffer::factory()->forSite($this->site)->create([
                'status' => OfferStatus::Draft,
                'visibility' => OfferVisibility::Public,
            ]);

            expect(app(ResolvePublicOfferQuery::class)->find($offer->id))->toBeNull();
        });
    });

    describe('applicationStatusForOffer', function (): void {
        test('returns the status enum for an existing network application', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $offer->update(['requires_approval' => false]);
            $affiliate = createTestAffiliate();

            app(ApplyToOffer::class)->execute($offer->id, (string) $affiliate->getKey());

            $status = app(ApplicationStatusQuery::class)->applicationStatusForOffer($offer->id, (string) $affiliate->getKey());

            expect($status)->toBe(ApplicationStatus::Approved);
        });

        test('returns null when no application exists', function (): void {
            $offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
            $affiliate = createTestAffiliate();

            expect(app(ApplicationStatusQuery::class)->applicationStatusForOffer($offer->id, (string) $affiliate->getKey()))->toBeNull();
        });
    });
});
