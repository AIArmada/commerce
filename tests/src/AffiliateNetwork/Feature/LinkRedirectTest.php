<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Links\Actions\UpdateLink;
use AIArmada\Links\Events\LinkBlocked;
use AIArmada\Links\Models\LinkClick;
use Illuminate\Support\Facades\Event;

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

    test('service links stay clean and record attributed clicks beyond the signature TTL', function (): void {
        config(['links.routing.signature_ttl_minutes' => 60 * 24 * 30]);
        $service = app(OfferLinkService::class);
        $link = $service->createLink($this->offer, (string) $this->affiliate->getKey(), ['sub_id' => 'bio']);
        $url = $service->generateTrackingUrl($link);

        expect($url)->toBe(route('links.redirect', ['slug' => $link->link->slug]))
            ->and(parse_url($url, PHP_URL_QUERY))->toBeNull()
            ->and($link->link->expires_at)->toBeNull();

        $this->get($url)->assertStatus(302);
        $this->travel(365)->days();
        expect($service->generateTrackingUrl($link->fresh()))->toBe($url);
        $response = $this->get($url);
        $response->assertStatus(302);
        expect($response->headers->get('Location'))->toContain('anl=' . $link->link->slug)
            ->toContain('sub1=bio')
            ->and($link->fresh()->clicks)->toBe(2)
            ->and(LinkClick::query()->forSubject($link)->count())->toBe(2);
    });

    test('unsigned service links still enforce row controls', function (string $control): void {
        $service = app(OfferLinkService::class);
        $link = $service->createLink($this->offer, (string) $this->affiliate->getKey());
        $url = $service->generateTrackingUrl($link);
        $attributes = match ($control) {
            'deactivation' => ['deactivated_at' => now()],
            'expiry' => ['expires_at' => now()->subMinute()],
            'click limit' => ['max_clicks' => 1],
        };
        UpdateLink::run($link->link, $attributes);
        if ($control === 'click limit') {
            $this->get($url)->assertStatus(302);
        }

        $this->get($url)->assertGone();
        expect(LinkClick::query()->forSubject($link)->count())->toBe($control === 'click limit' ? 1 : 0);
    })->with(['deactivation', 'expiry', 'click limit']);

    test('tracking url uses the public links route with the slug', function (): void {
        config(['affiliate-network.links.parameter' => 'anl']);

        $service = app(OfferLinkService::class);

        $link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->withSlug('public-slug')
            ->withTarget('https://merchant.example/offers/spring')
            ->create();

        $trackingUrl = $service->generateTrackingUrl($link);

        expect($trackingUrl)->toBe(route('links.redirect', ['slug' => 'public-slug']));
    });

    test('public redirect records a click and preserves attribution', function (): void {
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

    test('public redirect stores a raw click event with request context', function (): void {
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

    test('unknown slugs return not found', function (): void {
        $this->get('/go/no-such-network-slug')->assertNotFound();
    });
});
