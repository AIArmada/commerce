<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

describe('CreateOffer', function (): void {
    beforeEach(function (): void {
        $this->action = app(CreateOffer::class);
        $this->site = AffiliateSite::factory()->verified()->create();
    });

    test('creates offer with required data', function (): void {
        Event::fake();

        $offer = $this->action->execute($this->site->toRecord(), [
            'name' => 'Test Offer',
        ]);

        expect($offer)->toBeInstanceOf(AffiliateOffer::class);
        expect($offer->name)->toBe('Test Offer');
        expect($offer->site_id)->toBe($this->site->id);
        expect($offer->slug)->toBe('test-offer');

        Event::assertDispatched(OfferCreated::class, fn (OfferCreated $event): bool => $event->offerId === (string) $offer->getKey());
    });

    test('creates offer with draft status by default', function (): void {
        $offer = $this->action->execute($this->site->toRecord(), ['name' => 'Test Offer']);

        expect($offer->status)->toBe(OfferStatus::Draft);
    });

    test('creates offer with explicit status', function (): void {
        $offer = $this->action->execute($this->site->toRecord(), [
            'name' => 'Test Offer',
            'status' => OfferStatus::Draft,
        ]);

        expect($offer->status)->toBe(OfferStatus::Draft);
    });

    test('creates offer with custom slug', function (): void {
        $offer = $this->action->execute($this->site->toRecord(), [
            'name' => 'Test Offer',
            'slug' => 'custom-slug',
        ]);

        expect($offer->slug)->toBe('custom-slug');
    });

    test('requires a name', function (): void {
        $this->action->execute($this->site->toRecord(), []);
    })->throws(ValidationException::class);

    test('rejects negative rates and invalid urls', function (): void {
        expect(fn (): mixed => $this->action->execute($this->site->toRecord(), [
            'name' => 'Bad Rate',
            'rate_base_bp' => -5,
        ]))->toThrow(ValidationException::class);

        expect(fn (): mixed => $this->action->execute($this->site->toRecord(), [
            'name' => 'Bad Url',
            'landing_url' => 'not-a-url',
        ]))->toThrow(ValidationException::class);
    });

    test('normalizes currency to upper case', function (): void {
        $offer = $this->action->execute($this->site->toRecord(), [
            'name' => 'Cased Offer',
            'currency' => 'myr',
        ]);

        expect($offer->currency)->toBe('MYR');
    });
});
