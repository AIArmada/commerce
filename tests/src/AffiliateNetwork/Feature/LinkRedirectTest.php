<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Links\Events\LinkBlocked;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

describe('affiliate network redirect', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'merchant.example',
        ]);

        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'landing_url' => 'https://merchant.example/offers/spring',
            'requires_approval' => false,
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

    test('tracking url uses the signed links route with the slug', function (): void {
        config(['affiliate-network.links.parameter' => 'anl']);

        $service = app(OfferLinkService::class);

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('signed-slug')
            ->withTarget('https://merchant.example/offers/spring')
            ->create();

        $trackingUrl = $service->generateTrackingUrl($link);

        expect($trackingUrl)->toContain('/go/signed-slug');
        expect($trackingUrl)->toContain('signature=');
        expect($trackingUrl)->toContain('expires=');
    });

    test('redirect route rejects unsigned requests', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('unsigned-slug')
            ->create();

        $response = $this->get(route('links.redirect', ['slug' => $link->link->slug]));

        $response->assertForbidden();
        expect(LinkClick::query()->count())->toBe(0);
    });

    test('valid signed redirect records a click and preserves attribution', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('click-slug')
            ->withTarget('https://merchant.example/offers/spring')
            ->withSubIds('news', 'mail', 'hero')
            ->create();

        $response = $this->get(app(OfferLinkService::class)->generateTrackingUrl($link));

        $response->assertRedirect();
        expect($response->headers->get('Location'))->toContain('anl=click-slug')
            ->and($response->headers->get('Location'))->toContain('sub1=news')
            ->and($link->fresh()->clicks)->toBe(1);
    });

    test('valid signed redirect stores a raw click event with request context', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('event-slug')
            ->create();

        $response = $this->get(
            app(OfferLinkService::class)->generateTrackingUrl($link),
            [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'Referer' => 'https://publisher.example/review',
            ],
        );

        $response->assertRedirect();

        $event = LinkClick::query()->forSubject($link)->sole();

        expect($event->link_id)->toBe($link->link_id)
            ->and($event->ip_address)->toBe('127.0.0.1')
            ->and($event->user_agent)->toBe('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')
            ->and($event->referrer)->toBe('https://publisher.example/review')
            ->and($event->is_bot)->toBeFalse();
    });

    test('unverified-site links return gone without recording a click', function (): void {
        Event::fake([LinkBlocked::class]);

        $site = AffiliateSite::factory()->suspended()->create([
            'domain' => 'suspended-redirect.example',
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://suspended-redirect.example/deal',
        ]);
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('suspended-slug')
            ->withTarget('https://suspended-redirect.example/deal')
            ->create();

        $response = $this->get(app(OfferLinkService::class)->generateTrackingUrl($link));

        $response->assertGone();
        expect($link->fresh()->clicks)->toBe(0)
            ->and(LinkClick::query()->count())->toBe(0);

        Event::assertDispatched(LinkBlocked::class, fn (LinkBlocked $event): bool => $event->reason === 'site_unverified');
    });

    test('inactive offers return gone', function (): void {
        $offer = AffiliateOffer::factory()->draft()->forSite($this->site)->create();
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('draft-slug')
            ->create();

        $this->get(app(OfferLinkService::class)->generateTrackingUrl($link))->assertGone();

        expect($link->fresh()->clicks)->toBe(0);
    });

    test('inactive links return gone', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->inactive()
            ->withSlug('inactive-slug')
            ->create();

        $this->get(app(OfferLinkService::class)->generateTrackingUrl($link))->assertGone();

        expect($link->fresh()->clicks)->toBe(0);
    });

    test('approval-gated offers reject unapproved affiliates', function (): void {
        $this->offer->update(['requires_approval' => true]);

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('gated-slug')
            ->create();

        $this->get(app(OfferLinkService::class)->generateTrackingUrl($link))->assertGone();

        expect($link->fresh()->clicks)->toBe(0);
    });

    test('approval-gated offers redirect approved affiliates', function (): void {
        $this->offer->update(['requires_approval' => true]);

        AffiliateOfferApplication::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->approved()
            ->create();

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('approved-slug')
            ->withTarget('https://merchant.example/offers/spring')
            ->create();

        $response = $this->get(app(OfferLinkService::class)->generateTrackingUrl($link));

        $response->assertRedirect();
        expect($link->fresh()->clicks)->toBe(1);
    });

    test('bot hits redirect without incrementing the counter', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('bot-slug')
            ->withTarget('https://merchant.example/offers/spring')
            ->create();

        $response = $this->get(
            app(OfferLinkService::class)->generateTrackingUrl($link),
            ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
        );

        $response->assertRedirect();
        expect($link->fresh()->clicks)->toBe(0)
            ->and(LinkClick::query()->forSubject($link)->sole()->is_bot)->toBeTrue();
    });

    test('expired link returns gone without recording a click', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->expired()
            ->withSlug('expired-slug')
            ->create();

        $response = $this->get(app(OfferLinkService::class)->generateTrackingUrl($link));

        $response->assertGone();
        expect($link->fresh()->clicks)->toBe(0)
            ->and(LinkClick::query()->count())->toBe(0);
    });

    test('expired signed URL and tampered query are rejected', function (): void {
        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('signed-expiry-slug')
            ->create();

        $expiredUrl = URL::temporarySignedRoute(
            'links.redirect',
            now()->subMinute(),
            ['slug' => $link->link->slug],
        );

        $this->get($expiredUrl)->assertForbidden();

        $validUrl = app(OfferLinkService::class)->generateTrackingUrl($link);
        $this->get($validUrl . '&tampered=1')->assertForbidden();
        expect($link->fresh()->clicks)->toBe(0)
            ->and(LinkClick::query()->count())->toBe(0);
    });

    test('unknown slugs return not found', function (): void {
        $this->get('/go/no-such-network-slug')->assertNotFound();
    });
});
