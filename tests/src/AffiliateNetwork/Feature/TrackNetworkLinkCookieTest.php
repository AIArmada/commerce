<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

describe('TrackNetworkLinkCookie middleware', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'cookie.example',
        ]);

        $this->offer = AffiliateOffer::factory()->active()->forSite($this->site)->create();

        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Cookie Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('tracks custom primary link query parameter in cookie', function (): void {
        config([
            'affiliate-network.links.parameter' => 'partner',
            'affiliate-network.cookies.query_parameters' => ['legacy'],
            'affiliate-network.cookies.name' => 'affiliate_network_link',
        ]);

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliate($this->affiliate)
            ->create([
                'code' => 'cookie-link',
                'target_url' => 'https://cookie.example/offers/test',
            ]);

        $request = Request::create('/landing', 'GET', ['partner' => $link->code]);

        $response = app(TrackNetworkLinkCookie::class)->handle(
            $request,
            static fn (): Response => new Response('ok'),
        );

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === 'affiliate_network_link');

        expect($cookie)->not->toBeNull();
        expect(urldecode((string) $cookie->getValue()))->toContain('cookie-link');
    });
});
