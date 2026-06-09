<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use Illuminate\Support\Facades\Event;

describe('UpdateOffer', function (): void {
    beforeEach(function (): void {
        $this->action = app(UpdateOffer::class);
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->forSite($this->site)->create([
            'name' => 'Original Name',
            'description' => 'Original description',
        ]);
    });

    test('updates offer fields', function (): void {
        $updated = $this->action->execute($this->offer, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        expect($updated->name)->toBe('Updated Name');
        expect($updated->description)->toBe('Updated description');
    });

    test('returns fresh model instance', function (): void {
        $updated = $this->action->execute($this->offer, [
            'name' => 'Updated',
        ]);

        expect($updated->name)->toBe('Updated');
        expect($updated->isDirty())->toBeFalse();
    });

    test('dispatches OfferUpdated event', function (): void {
        Event::fake();

        $this->action->execute($this->offer, ['name' => 'Event Test']);

        Event::assertDispatched(OfferUpdated::class);
    });
});
