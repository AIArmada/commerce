<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

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

    test('creates offer with draft status when approval required', function (): void {
        config(['affiliate-network.offers.require_approval' => true]);

        $offer = $this->action->execute($this->site, ['name' => 'Test Offer']);

        expect($offer->status)->toBe(OfferStatus::Draft);
    });

    test('creates offer with draft status when approval not required', function (): void {
        config(['affiliate-network.offers.require_approval' => false]);

        $offer = $this->action->execute($this->site, ['name' => 'Test Offer']);

        expect($offer->status)->toBe(OfferStatus::Draft);
    });

    test('creates offer with explicit status', function (): void {
        $offer = $this->action->execute($this->site, [
            'name' => 'Test Offer',
            'status' => OfferStatus::Draft,
        ]);

        expect($offer->status)->toBe(OfferStatus::Draft);
    });

    test('creates offer with custom slug', function (): void {
        $offer = $this->action->execute($this->site, [
            'name' => 'Test Offer',
            'slug' => 'custom-slug',
        ]);

        expect($offer->slug)->toBe('custom-slug');
    });

    test('requires a name', function (): void {
        $this->action->execute($this->site, []);
    })->throws(ValidationException::class);

    test('rejects negative rates and invalid urls', function (): void {
        expect(fn (): mixed => $this->action->execute($this->site, [
            'name' => 'Bad Rate',
            'rate_base_bp' => -5,
        ]))->toThrow(ValidationException::class);

        expect(fn (): mixed => $this->action->execute($this->site, [
            'name' => 'Bad Url',
            'landing_url' => 'not-a-url',
        ]))->toThrow(ValidationException::class);
    });

    test('normalizes currency to upper case', function (): void {
        $offer = $this->action->execute($this->site, [
            'name' => 'Cased Offer',
            'currency' => 'myr',
        ]);

        expect($offer->currency)->toBe('MYR');
    });
});
