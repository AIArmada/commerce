<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Models\Link;
use Illuminate\Support\Facades\Log;

describe('OfferLinkService', function (): void {
    beforeEach(function (): void {
        $this->service = app(OfferLinkService::class);
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

            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey());

            expect($link)->toBeInstanceOf(AffiliateOfferLink::class);
            expect($link->offer_id)->toBe($this->offer->id);
            expect($link->affiliate_id)->toBe($this->affiliate->id);
            expect($link->site_id)->toBe($this->site->id);
            expect($link->is_active)->toBeTrue();
            expect($link->link)->toBeInstanceOf(Link::class);
            expect($link->link->destination_url)->toBe('https://example.com/landing');
            expect($link->link->require_signature)->toBeTrue();
            expect($link->link->subject_id)->toBe((string) $link->getKey());
            expect($link->link->parameters['anl'])->toBe($link->link->slug);
        });

        test('creates link with custom target URL', function (): void {
            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
                'target_url' => 'https://custom.com/page',
            ]);

            expect($link->link->destination_url)->toBe('https://custom.com/page');
        });

        test('allows plain http merchant urls', function (): void {
            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
                'target_url' => 'http://legacy-merchant.example/page',
            ]);

            expect($link->link->destination_url)->toBe('http://legacy-merchant.example/page');
        });

        test('creates link with sub IDs', function (): void {
            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
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

            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
                'custom_parameters' => 'utm_source=affiliate&utm_medium=banner&anl=spoofed',
            ]);

            expect($link->link->parameters['utm_source'])->toBe('affiliate');
            expect($link->link->parameters['utm_medium'])->toBe('banner');
            expect($link->link->parameters['anl'])->toBe($link->link->slug);
        });

        test('creates link with expiration', function (): void {
            $expiresAt = now()->addDays(30);

            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
                'expires_at' => $expiresAt,
            ]);

            expect($link->link->expires_at->toDateString())->toBe($expiresAt->toDateString());
            expect($link->isExpired())->toBeFalse();
        });

        test('creates link with metadata', function (): void {
            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
                'metadata' => ['campaign' => 'spring_sale'],
            ]);

            expect($link->metadata)->toBe(['campaign' => 'spring_sale']);
        });

        test('falls back to site domain when no landing URL', function (): void {
            $this->offer->update(['landing_url' => null]);

            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey());

            expect($link->link->destination_url)->toBe('https://example.com/');
        });

        test('refuses links for inactive offers', function (): void {
            $draft = AffiliateOffer::factory()->draft()->forSite($this->site)->create([
                'requires_approval' => false,
            ]);

            $this->service->createLink($draft, (string) $this->affiliate->getKey());
        })->throws(RuntimeException::class, 'active');

        test('refuses links without approval', function (): void {
            $this->offer->update(['requires_approval' => true]);

            $this->service->createLink($this->offer, (string) $this->affiliate->getKey());
        })->throws(RuntimeException::class, 'not approved');

        test('creates links for approved affiliates', function (): void {
            $this->offer->update(['requires_approval' => true]);

            AffiliateOfferApplication::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->approved()
                ->create();

            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey());

            expect($link->offer_id)->toBe($this->offer->id);
        });

        test('refuses non-http target urls', function (): void {
            $this->service->createLink($this->offer, (string) $this->affiliate->getKey(), [
                'target_url' => 'javascript:alert(1)',
            ]);
        })->throws(RuntimeException::class, 'http(s)');

        test('stamps the offer currency on the link', function (): void {
            $this->offer->update(['currency' => 'EUR']);

            $link = $this->service->createLink($this->offer, (string) $this->affiliate->getKey());

            expect($link->currency)->toBe('EUR');
        });
    });

    describe('generateTrackingUrl', function (): void {
        test('generates signed tracking URL', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withSlug('trackslug')
                ->create();

            $url = $this->service->generateTrackingUrl($link);

            expect($url)->toContain('/go/trackslug');
            expect($url)->toContain('signature=');
            expect($url)->toContain('expires=');
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

            $resolved = $this->service->resolveLink('findme123');

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

            $resolved = $this->service->resolveLink('inactive123');

            expect($resolved)->toBeNull();
        });

        test('returns null for non-existent slug', function (): void {
            $resolved = $this->service->resolveLink('nonexistent');

            expect($resolved)->toBeNull();
        });

        test('eager loads relationships', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->forSite($this->site)
                ->withSlug('eager123')
                ->create();

            $resolved = $this->service->resolveLink('eager123');

            expect($resolved->relationLoaded('link'))->toBeTrue();
            expect($resolved->relationLoaded('offer'))->toBeTrue();
            expect($resolved->relationLoaded('affiliate'))->toBeFalse();
            expect($resolved->relationLoaded('site'))->toBeTrue();
        });

        test('resolves tenant-owned link and relations under owner mode', function (): void {
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

            $resolved = $this->service->resolveLink('owned-link-slug');

            expect($resolved)->not->toBeNull()
                ->and($resolved->offer->id)->toBe($offer->id)
                ->and($resolved->affiliate->id)->toBe($affiliate->id)
                ->and($resolved->site?->id)->toBe($site->id);
        });
    });

    describe('recordConversion', function (): void {
        test('increments conversion and revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(0, 5, 10000)
                ->create();

            $this->service->recordConversion($link, 5000);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(6);
            expect($fresh->revenue)->toBe(15000);
        });

        test('records conversion without revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            $this->service->recordConversion($link, 0);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(1);
            expect($fresh->revenue)->toBe(0);
        });

        test('aggregates revenue when currencies match', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            $this->service->recordConversion($link, 5000, $link->currency);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(1);
            expect($fresh->revenue)->toBe(5000);
        });

        test('counts the conversion but skips revenue on currency mismatch', function (): void {
            Log::spy();

            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create(['currency' => 'USD']);

            $this->service->recordConversion($link, 5000, 'MYR');

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(1);
            expect($fresh->revenue)->toBe(0);

            Log::shouldHaveReceived('warning')->once();
        });
    });

    describe('getStats', function (): void {
        test('calculates stats correctly', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(1000, 50, 250000)
                ->create();

            $stats = $this->service->getStats($link);

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

            $stats = $this->service->getStats($link);

            expect($stats['conversion_rate'])->toBe(0.0);
            expect($stats['revenue_per_click'])->toBe(0.0);
        });

        test('handles high conversion rate', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(10, 8, 80000)
                ->create();

            $stats = $this->service->getStats($link);

            expect($stats['conversion_rate'])->toBe(80.0);
        });
    });
});
