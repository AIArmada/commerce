<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
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
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withStats(0, 5, 10000)
            ->create();
    });

    test('increments conversion and revenue', function (): void {
        $this->action->execute(
            offerId: (string) $this->offer->id,
            affiliateId: (string) $this->affiliate->getKey(),
            revenueMinor: 5000,
            currency: 'MYR',
            externalReference: 'REC-CONV-1',
            linkId: (string) $this->link->id,
        );

        $fresh = $this->link->fresh();
        expect($fresh->conversions)->toBe(6);
        expect($fresh->revenue)->toBe(15000);
    });

    test('records conversion without revenue', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->create();

        $this->action->execute(
            offerId: (string) $this->offer->id,
            affiliateId: (string) $this->affiliate->getKey(),
            revenueMinor: 0,
            currency: 'MYR',
            externalReference: 'REC-CONV-2',
            linkId: (string) $link->id,
        );

        $fresh = $link->fresh();
        expect($fresh->conversions)->toBe(1);
        expect($fresh->revenue)->toBe(0);
    });

    test('dispatches NetworkConversionRecorded event', function (): void {
        Event::fake();

        $this->action->execute(
            offerId: (string) $this->offer->id,
            affiliateId: (string) $this->affiliate->getKey(),
            revenueMinor: 5000,
            currency: 'MYR',
            externalReference: 'REC-CONV-3',
            linkId: (string) $this->link->id,
        );

        Event::assertDispatched(
            NetworkConversionRecorded::class,
            fn (NetworkConversionRecorded $event): bool => $event->leg->linkId === (string) $this->link->id
                && $event->counterRevenueMinor === 5000,
        );
    });
});
