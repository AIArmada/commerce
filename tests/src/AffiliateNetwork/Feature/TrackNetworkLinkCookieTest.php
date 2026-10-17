<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Http\Middleware\TrackNetworkLinkCookie;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

describe('TrackNetworkLinkCookie middleware', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'cookie.example',
        ]);

        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();

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
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('cookie-link')
            ->withTarget('https://cookie.example/offers/test')
            ->create();

        $request = Request::create('/landing', 'GET', ['partner' => $link->link->slug]);

        $response = app(TrackNetworkLinkCookie::class)->handle(
            $request,
            static fn (): Response => new Response('ok'),
        );

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === 'affiliate_network_link');

        expect($cookie)->not->toBeNull();
        expect(decrypt(urldecode((string) $cookie->getValue())))->toContain('cookie-link');
    });

    test('persists the validated click snapshot to the current cart', function (): void {
        config([
            'affiliate-network.links.parameter' => 'anl',
            'affiliate-network.cookies.name' => 'affiliate_network_link',
        ]);

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('durable-cookie-link')
            ->create();
        $cart = new Cart(new InMemoryStorage, 'network-snapshot-cart', events: null, eventsEnabled: false);
        $cartManager = mock(CartManagerInterface::class);
        $cartManager->shouldReceive('getCurrentCart')->twice()->andReturn($cart);
        app()->instance(CartManagerInterface::class, $cartManager);

        $response = app(TrackNetworkLinkCookie::class)->handle(
            Request::create('/landing', 'GET', ['anl' => $link->link->slug]),
            static fn (): Response => new Response('ok'),
        );

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === 'affiliate_network_link');
        $version = $cart->getVersion();

        app(TrackNetworkLinkCookie::class)->handle(
            Request::create('/landing', 'GET', [], [
                'affiliate_network_link' => urldecode((string) $cookie?->getValue()),
            ]),
            static fn (): Response => new Response('ok'),
        );

        $snapshot = $cart->getAllMetadata()['network_attribution'];

        expect($snapshot)->toMatchArray([
            'link_code' => $link->trackedSlug(),
            'affiliate_id' => (string) $this->affiliate->getKey(),
            'offer_id' => (string) $this->offer->getKey(),
            'signature' => hash_hmac(
                'sha256',
                implode('|', [
                    (string) $link->trackedSlug(),
                    (string) $this->affiliate->getKey(),
                    (string) $this->offer->getKey(),
                    $snapshot['clicked_at'],
                ]),
                (string) config('app.key'),
            ),
        ])
            ->and($snapshot['clicked_at'])->toBeString()
            ->and($cart->getVersion())->toBe($version);
    });

    test('does not create a cart snapshot on a page view with only an existing cookie', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('cookie-only-page-view')
            ->create();
        $cookieValue = encrypt(json_encode([
            'code' => $link->trackedSlug(),
            'affiliate_id' => (string) $this->affiliate->getKey(),
            'offer_id' => (string) $this->offer->getKey(),
            'clicked_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        $cart = new Cart(new InMemoryStorage, 'network-cookie-only-cart', events: null, eventsEnabled: false);
        $cartManager = mock(CartManagerInterface::class);
        $cartManager->shouldReceive('getCurrentCart')->once()->andReturn($cart);
        app()->instance(CartManagerInterface::class, $cartManager);

        app(TrackNetworkLinkCookie::class)->handle(
            Request::create('/page', 'GET', [], ['affiliate_network_link' => $cookieValue]),
            static fn (): Response => new Response('ok'),
        );

        expect($cart->exists())->toBeFalse()
            ->and($cart->getAllMetadata())->toBe([]);
    });

    test('rejects plaintext, tampered, and malformed cookie values', function (): void {
        $valid = encrypt(json_encode([
            'code' => 'real-link',
            'affiliate_id' => 'aff-1',
            'offer_id' => 'offer-1',
            'clicked_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        expect(TrackNetworkLinkCookie::parseCookie($valid))->toMatchArray(['code' => 'real-link'])
            ->and(TrackNetworkLinkCookie::parseCookie(json_encode(['code' => 'forged'])))->toBeNull()
            ->and(TrackNetworkLinkCookie::parseCookie(mb_substr($valid, 0, -4) . 'abcd'))->toBeNull()
            ->and(TrackNetworkLinkCookie::parseCookie(encrypt('not-json')))->toBeNull()
            ->and(TrackNetworkLinkCookie::parseCookie('garbage'))->toBeNull();
    });
});
