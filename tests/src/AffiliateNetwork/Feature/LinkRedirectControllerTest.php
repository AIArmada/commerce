<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\URL;

describe('affiliate network redirect route', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'merchant.example',
        ]);

        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
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

    test('valid signed redirect records a click and preserves attribution code', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create([
                'code' => 'click-link',
                'target_url' => 'https://merchant.example/offers/spring',
            ]);

        $response = $this->get(app(OfferLinkService::class)->generateTrackingUrl($link));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('anl=click-link')
            ->and($link->fresh()->clicks)->toBe(1);
    });

    test('expired link returns gone without recording a click', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->expired()
            ->create(['code' => 'expired-link']);

        $response = $this->get(app(OfferLinkService::class)->generateTrackingUrl($link));

        $response->assertGone();
        expect($link->fresh()->clicks)->toBe(0);
    });

    test('expired signed URL and forged code are rejected', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create(['code' => 'signed-expiry-link']);

        $expiredUrl = URL::temporarySignedRoute(
            'affiliate-network.redirect',
            now()->subMinute(),
            ['code' => $link->code, 'anl' => $link->code],
        );

        $this->get($expiredUrl)->assertForbidden();

        $validUrl = app(OfferLinkService::class)->generateTrackingUrl($link);
        $this->get(str_replace($link->code, 'forged-code', $validUrl))->assertForbidden();
        expect($link->fresh()->clicks)->toBe(0);
    });

    test('redirect route keeps its explicit rate limit', function (): void {
        $route = app('router')->getRoutes()->getByName('affiliate-network.redirect');

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('throttle:60,1');
    });
});
