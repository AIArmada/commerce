<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\Event;

describe('CreateOffer', function (): void {
    beforeEach(function (): void {
        $this->action = app(CreateOffer::class);
        $this->site = AffiliateSite::factory()->verified()->create();
    });

    test('creates offer with required data', function (): void {
        Event::fake();

        $offer = $this->action->execute($this->site, [
            'name' => 'Test Offer',
        ]);

        expect($offer)->toBeInstanceOf(AffiliateOffer::class);
        expect($offer->name)->toBe('Test Offer');
        expect($offer->site_id)->toBe($this->site->id);
        expect($offer->slug)->toBe('test-offer');

        Event::assertDispatched(OfferCreated::class, fn (OfferCreated $event): bool => $event->offer->id === $offer->id);
    });

    test('dispatches OfferCreated event', function (): void {
        Event::fake();

        $this->action->execute($this->site, ['name' => 'Event Test']);

        Event::assertDispatched(OfferCreated::class);
    });

    test('creates offer with pending status when approval required', function (): void {
        config(['affiliate-network.offers.require_approval' => true]);

        $offer = $this->action->execute($this->site, ['name' => 'Test Offer']);

        expect($offer->status)->toBe(AffiliateOffer::STATUS_PENDING);
    });

    test('creates offer with active status when approval not required', function (): void {
        config(['affiliate-network.offers.require_approval' => false]);

        $offer = $this->action->execute($this->site, ['name' => 'Test Offer']);

        expect($offer->status)->toBe(AffiliateOffer::STATUS_ACTIVE);
    });

    test('creates offer with explicit status', function (): void {
        $offer = $this->action->execute($this->site, [
            'name' => 'Test Offer',
            'status' => AffiliateOffer::STATUS_DRAFT,
        ]);

        expect($offer->status)->toBe(AffiliateOffer::STATUS_DRAFT);
    });

    test('creates offer with custom slug', function (): void {
        $offer = $this->action->execute($this->site, [
            'name' => 'Test Offer',
            'slug' => 'custom-slug',
        ]);

        expect($offer->slug)->toBe('custom-slug');
    });
});
