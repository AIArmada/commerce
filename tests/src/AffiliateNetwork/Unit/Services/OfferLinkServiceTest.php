<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateOfferLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RecordLinkCounters;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Queries\LinkStatsQuery;
use AIArmada\AffiliateNetwork\Features\Attribution\Queries\ResolveOfferLinkQuery;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\GenerateLinkUrl;
use AIArmada\Links\Models\Link;

describe('offer link actions', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'example.com',
        ]);
        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'landing_url' => 'https://example.com/landing',
            'requires_approval' => false,
        ]);
        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    describe('createLink', function (): void {
        test('creates link with default values', function (): void {
            config(['affiliate-network.links.parameter' => 'anl']);

            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey());

            expect($link)->toBeInstanceOf(AffiliateOfferLink::class);
            expect($link->offer_id)->toBe($this->offer->id);
            expect($link->affiliate_id)->toBe($this->affiliate->id);
            expect($link->site_id)->toBe($this->site->id);
            expect($link->is_active)->toBeTrue();
            expect($link->link)->toBeInstanceOf(Link::class);
            expect($link->link->destination_url)->toBe('https://example.com/landing');
            expect($link->link->require_signature)->toBeFalse();
            expect($link->link->subject_id)->toBe((string) $link->getKey());
            expect($link->link->parameters['anl'])->toBe($link->link->slug);
        });

        test('creates link with custom target URL on the verified domain', function (): void {
            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'target_url' => 'https://shop.example.com/page',
            ]);

            expect($link->link->destination_url)->toBe('https://shop.example.com/page');
        });

        test('allows plain http merchant urls on the verified domain', function (): void {
            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'target_url' => 'http://example.com/page',
            ]);

            expect($link->link->destination_url)->toBe('http://example.com/page');
        });

        test('creates link with sub IDs', function (): void {
            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'sub_id' => 'campaign1',
                'sub_id_2' => 'source',
                'sub_id_3' => 'creative',
            ]);

            expect($link->sub_id)->toBe('campaign1');
            expect($link->sub_id_2)->toBe('source');
            expect($link->sub_id_3)->toBe('creative');
            expect($link->link->parameters['sub1'])->toBe('campaign1');
            expect($link->link->parameters['sub2'])->toBe('source');
            expect($link->link->parameters['sub3'])->toBe('creative');
        });

        test('creates link with custom parameters', function (): void {
            config(['affiliate-network.links.parameter' => 'anl']);

            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'custom_parameters' => 'utm_source=affiliate&utm_medium=banner&anl=spoofed',
            ]);

            expect($link->link->parameters['utm_source'])->toBe('affiliate');
            expect($link->link->parameters['utm_medium'])->toBe('banner');
            expect($link->link->parameters['anl'])->toBe($link->link->slug);
        });

        test('creates link with expiration', function (): void {
            $expiresAt = now()->addDays(30);

            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'expires_at' => $expiresAt,
            ]);

            expect($link->link->expires_at->toDateString())->toBe($expiresAt->toDateString());
            expect($link->isExpired())->toBeFalse();
        });

        test('creates link with metadata', function (): void {
            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'metadata' => ['campaign' => 'spring_sale'],
            ]);

            expect($link->metadata)->toBe(['campaign' => 'spring_sale']);
        });

        test('falls back to site domain when no landing URL', function (): void {
            $this->offer->update(['landing_url' => null]);

            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey());

            expect($link->link->destination_url)->toBe('https://example.com/');
        });

        test('refuses links for inactive offers', function (): void {
            $draft = AffiliateOffer::factory()->draft()->forSite($this->site)->create([
                'requires_approval' => false,
            ]);

            app(CreateOfferLink::class)->execute($draft->id, (string) $this->affiliate->getKey());
        })->throws(RuntimeException::class, 'active');

        test('refuses links without approval', function (): void {
            $this->offer->update(['requires_approval' => true]);

            app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey());
        })->throws(RuntimeException::class, 'not approved');

        test('creates links for approved affiliates', function (): void {
            $this->offer->update(['requires_approval' => true]);

            AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->approved()
                ->create();

            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey());

            expect($link->offer_id)->toBe($this->offer->id);
        });

        test('refuses target urls outside the verified domain', function (): void {
            app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey(), [
                'target_url' => 'javascript:alert(1)',
            ]);
        })->throws(RuntimeException::class);

        test('stamps the offer currency on the link', function (): void {
            $this->offer->update(['currency' => 'EUR']);

            $link = app(CreateOfferLink::class)->execute($this->offer->id, (string) $this->affiliate->getKey());

            expect($link->currency)->toBe('EUR');
        });
    });

    describe('generateTrackingUrl', function (): void {
        test('generates a clean public tracking URL', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withSlug('trackslug')
                ->create();

            $url = GenerateLinkUrl::run($link->link);

            expect($url)->toBe(route('links.redirect', ['slug' => 'trackslug']));
        });
    });

    describe('resolveLink', function (): void {
        test('resolves active link by slug', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->active()
                ->withSlug('findme123')
                ->create();

            $resolved = app(ResolveOfferLinkQuery::class)->bySlug('findme123');

            expect($resolved)->not->toBeNull();
            expect($resolved->id)->toBe($link->id);
        });

        test('does not resolve inactive link', function (): void {
            AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->inactive()
                ->withSlug('inactive123')
                ->create();

            $resolved = app(ResolveOfferLinkQuery::class)->bySlug('inactive123');

            expect($resolved)->toBeNull();
        });

        test('returns null for non-existent slug', function (): void {
            $resolved = app(ResolveOfferLinkQuery::class)->bySlug('nonexistent');

            expect($resolved)->toBeNull();
        });

        test('eager loads the tracked link', function (): void {
            AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->forSite($this->site)
                ->withSlug('eager123')
                ->create();

            $resolved = app(ResolveOfferLinkQuery::class)->bySlug('eager123');

            expect($resolved->relationLoaded('link'))->toBeTrue();
        });

        test('resolves tenant-owned link under owner mode', function (): void {
            config([
                'affiliate-network.owner.enabled' => true,
                'affiliates.owner.enabled' => true,
            ]);

            $owner = User::factory()->create();

            $site = OwnerContext::withOwner($owner, fn () => AffiliateSite::factory()->verified()->forOwner($owner)->create([
                'domain' => 'owned-link.example',
            ]));

            $offer = OwnerContext::withOwner($owner, fn () => AffiliateOffer::factory()->published()->forSite($site)->create([
                'landing_url' => 'https://owned-link.example/offers/tenant',
            ]));

            $affiliate = OwnerContext::withOwner($owner, fn () => Affiliate::create([
                'code' => 'AFF' . uniqid(),
                'name' => 'Owned Link Affiliate',
                'status' => 'active',
                'commission_type' => 'percentage',
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]));

            OwnerContext::withOwner($owner, fn () => AffiliateOfferLink::factory()
                ->forOffer($offer)
                ->forAffiliateId((string) $affiliate->getKey())
                ->forSite($site)
                ->active()
                ->withSlug('owned-link-slug')
                ->create());

            $resolved = app(ResolveOfferLinkQuery::class)->bySlug('owned-link-slug');

            expect($resolved)->not->toBeNull()
                ->and($resolved->offer_id)->toBe($offer->id)
                ->and($resolved->affiliate_id)->toBe((string) $affiliate->getKey())
                ->and($resolved->site_id)->toBe($site->id);
        });
    });

    describe('recordConversion', function (): void {
        test('increments conversion and revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(0, 5, 10000)
                ->create();

            app(RecordLinkCounters::class)->execute((string) $link->getKey(), 5000, 1);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(6);
            expect($fresh->revenue)->toBe(15000);
        });

        test('records conversion without revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            app(RecordLinkCounters::class)->execute((string) $link->getKey(), 0, 1);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(1);
            expect($fresh->revenue)->toBe(0);
        });
    });

    describe('getStats', function (): void {
        test('calculates stats correctly', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(1000, 50, 250000)
                ->create();

            $stats = app(LinkStatsQuery::class)->execute((string) $link->getKey());

            expect($stats['clicks'])->toBe(1000);
            expect($stats['conversions'])->toBe(50);
            expect($stats['revenue'])->toBe(250000);
            expect($stats['conversion_rate'])->toBe(5.0);
            expect($stats['revenue_per_click'])->toBe(250.0);
        });

        test('handles zero clicks', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            $stats = app(LinkStatsQuery::class)->execute((string) $link->getKey());

            expect($stats['conversion_rate'])->toBe(0.0);
            expect($stats['revenue_per_click'])->toBe(0.0);
        });

        test('handles high conversion rate', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(10, 8, 80000)
                ->create();

            $stats = app(LinkStatsQuery::class)->execute((string) $link->getKey());

            expect($stats['conversion_rate'])->toBe(80.0);
        });
    });
});

test('network branded links snapshot the affiliate handle and preserve redirect attribution after rename', function (): void {
    $site = AffiliateSite::factory()->verified()->create();
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(['requires_approval' => false, 'name' => 'Summer']);
    $affiliate = createTestAffiliate(['handle' => 'saifreviews']);

    $offerLink = app(CreateOfferLink::class)->execute($offer->id, $affiliate->id, [
        'link_style' => 'branded',
        'link_label' => 'summer',
    ]);

    $issued = $offerLink->link->cloakedUrl();
    expect($issued)->toContain('/go/saifreviews/summer-');
    $affiliate->update(['handle' => 'saifnew']);
    expect($offerLink->fresh()->link->cloakedUrl())->toBe($issued);
    $this->get($issued)->assertRedirect();
    expect($offerLink->refresh()->clicks)->toBe(1)
        ->and(app(ResolveOfferLinkQuery::class)->bySlug($offerLink->link->slug)->affiliate_id)->toBe((string) $affiliate->id);

    $new = app(CreateOfferLink::class)->execute($offer->id, $affiliate->id, ['link_style' => 'branded']);
    expect($new->link->cloakedUrl())->toContain('/go/saifnew/');
});
