<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\Event;

describe('RecordNetworkConversion', function (): void {
    beforeEach(function (): void {
        $this->action = app(RecordNetworkConversion::class);
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        $this->link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create([
                'conversions' => 5,
                'revenue' => 10000,
            ]);
    });

    test('increments conversion and revenue', function (): void {
        $this->action->execute($this->link, 5000);

        $fresh = $this->link->fresh();
        expect($fresh->conversions)->toBe(6);
        expect($fresh->revenue)->toBe(15000);
    });

    test('records conversion without revenue', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create(['conversions' => 0, 'revenue' => 0]);

        $this->action->execute($link, 0);

        $fresh = $link->fresh();
        expect($fresh->conversions)->toBe(1);
        expect($fresh->revenue)->toBe(0);
    });

    test('dispatches NetworkConversionRecorded event', function (): void {
        Event::fake();

        $this->action->execute($this->link, 5000);

        Event::assertDispatched(NetworkConversionRecorded::class, fn (NetworkConversionRecorded $event): bool => $event->link->id === $this->link->id && $event->revenueMinor === 5000);
    });
});
