<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\Event;

describe('ApplyToOffer', function (): void {
    beforeEach(function (): void {
        $this->action = app(ApplyToOffer::class);
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
        $this->affiliate = createTestAffiliate();
    });

    test('creates pending application for offer requiring approval', function (): void {
        Event::fake();

        $this->offer->update(['requires_approval' => true]);

        $application = $this->action->execute($this->offer, $this->affiliate);

        expect($application)->toBeInstanceOf(AffiliateOfferApplication::class);
        expect($application->offer_id)->toBe($this->offer->id);
        expect($application->affiliate_id)->toBe($this->affiliate->id);
        expect($application->status)->toBe(ApplicationStatus::Pending);

        Event::assertDispatched(ApplicationSubmitted::class);
    });

    test('creates approved application for offer not requiring approval', function (): void {
        $this->offer->update(['requires_approval' => false]);

        $application = $this->action->execute($this->offer, $this->affiliate);

        expect($application->status)->toBe(ApplicationStatus::Approved);
        expect($application->reviewed_at)->not->toBeNull();
    });

    test('creates approved application when auto_approve enabled', function (): void {
        config(['affiliate-network.applications.auto_approve' => true]);

        $application = $this->action->execute($this->offer, $this->affiliate);

        expect($application->status)->toBe(ApplicationStatus::Approved);
    });

    test('includes reason in application', function (): void {
        $application = $this->action->execute(
            $this->offer,
            $this->affiliate,
            'I have a large audience',
        );

        expect($application->reason)->toBe('I have a large audience');
    });

    test('dispatches ApplicationSubmitted event', function (): void {
        Event::fake();

        $this->action->execute($this->offer, $this->affiliate);

        Event::assertDispatched(ApplicationSubmitted::class);
    });

    test('returns existing application if already exists', function (): void {
        $existing = AffiliateOfferApplication::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->pending()
            ->create();

        $application = $this->action->execute($this->offer, $this->affiliate);

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

        $application = $this->action->execute($this->offer, $this->affiliate);

        expect($application->id)->toBe($existing->id);
        expect($application->status)->toBe(ApplicationStatus::Pending);
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

        $this->action->execute($this->offer, $this->affiliate);
    })->throws(RuntimeException::class);
});
