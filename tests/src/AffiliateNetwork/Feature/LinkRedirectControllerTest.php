<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;

describe('affiliate network redirect route', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'merchant.example',
        ]);

        $this->offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'landing_url' => 'https://merchant.example/offers/spring',
        ]);

        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Redirect Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('redirect route rejects unsigned requests', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create([
                'code' => 'unsigned-link',
                'target_url' => 'https://merchant.example/offers/spring',
            ]);

        $response = $this->get(route('affiliate-network.redirect', ['code' => $link->code]));

        $response->assertForbidden();
    });

    test('tracking url uses signed redirect route with link code query parameter', function (): void {
        config(['affiliate-network.links.parameter' => 'anl']);

        $service = app(OfferLinkService::class);

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create([
                'code' => 'signed-link',
                'target_url' => 'https://merchant.example/offers/spring',
            ]);

        $trackingUrl = $service->generateTrackingUrl($link);

        expect($trackingUrl)->toContain('/affiliate-network/go/signed-link');
        expect($trackingUrl)->toContain('anl=signed-link');
        expect($trackingUrl)->toContain('signature=');
    });
});
