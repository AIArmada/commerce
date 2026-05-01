<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Support\Facades\URL;

describe('OfferLinkService', function (): void {
    beforeEach(function (): void {
        $this->service = app(OfferLinkService::class);
        $this->site = AffiliateSite::factory()->verified()->create([
            'domain' => 'example.com',
        ]);
        $this->offer = AffiliateOffer::factory()->active()->forSite($this->site)->create([
            'landing_url' => 'https://example.com/landing',
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
            $link = $this->service->createLink($this->offer, $this->affiliate);

            expect($link)->toBeInstanceOf(AffiliateOfferLink::class);
            expect($link->offer_id)->toBe($this->offer->id);
            expect($link->affiliate_id)->toBe($this->affiliate->id);
            expect($link->site_id)->toBe($this->site->id);
            expect($link->target_url)->toBe('https://example.com/landing');
            expect($link->code)->not->toBeEmpty();
            expect($link->is_active)->toBeTrue();
        });

        test('creates link with custom target URL', function (): void {
            $link = $this->service->createLink($this->offer, $this->affiliate, [
                'target_url' => 'https://custom.com/page',
            ]);

            expect($link->target_url)->toBe('https://custom.com/page');
        });

        test('creates link with sub IDs', function (): void {
            $link = $this->service->createLink($this->offer, $this->affiliate, [
                'sub_id' => 'campaign1',
                'sub_id_2' => 'source',
                'sub_id_3' => 'creative',
            ]);

            expect($link->sub_id)->toBe('campaign1');
            expect($link->sub_id_2)->toBe('source');
            expect($link->sub_id_3)->toBe('creative');
        });

        test('creates link with custom parameters', function (): void {
            $link = $this->service->createLink($this->offer, $this->affiliate, [
                'custom_parameters' => 'utm_source=affiliate&utm_medium=banner',
            ]);

            expect($link->custom_parameters)->toBe('utm_source=affiliate&utm_medium=banner');
        });

        test('creates link with expiration', function (): void {
            $expiresAt = now()->addDays(30);

            $link = $this->service->createLink($this->offer, $this->affiliate, [
                'expires_at' => $expiresAt,
            ]);

            expect($link->expires_at->toDateString())->toBe($expiresAt->toDateString());
        });

        test('creates link with metadata', function (): void {
            $link = $this->service->createLink($this->offer, $this->affiliate, [
                'metadata' => ['campaign' => 'spring_sale'],
            ]);

            expect($link->metadata)->toBe(['campaign' => 'spring_sale']);
        });

        test('falls back to site domain when no landing URL', function (): void {
            $this->offer->update(['landing_url' => null]);

            $link = $this->service->createLink($this->offer, $this->affiliate);

            expect($link->target_url)->toBe('https://example.com/');
        });
    });

    describe('buildDirectLink', function (): void {
        test('builds direct link with code parameter', function (): void {
            config(['affiliate-network.links.parameter' => 'anl']);

            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'code' => 'testcode123',
                    'target_url' => 'https://example.com/product',
                ]);

            $url = $this->service->buildDirectLink($link);

            expect($url)->toContain('anl=testcode123');
            expect($url)->toStartWith('https://example.com/product?');
        });

        test('builds direct link with existing query string', function (): void {
            config(['affiliate-network.links.parameter' => 'anl']);

            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'code' => 'testcode123',
                    'target_url' => 'https://example.com/product?existing=param',
                ]);

            $url = $this->service->buildDirectLink($link);

            expect($url)->toContain('&anl=testcode123');
        });

        test('includes sub IDs in direct link', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->withSubIds('campaign1', 'source', 'creative')
                ->create([
                    'target_url' => 'https://example.com/product',
                ]);

            $url = $this->service->buildDirectLink($link);

            expect($url)->toContain('sub1=campaign1');
            expect($url)->toContain('sub2=source');
            expect($url)->toContain('sub3=creative');
        });

        test('includes custom parameters in direct link', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'target_url' => 'https://example.com/product',
                    'custom_parameters' => 'utm_source=affiliate&utm_medium=banner',
                ]);

            $url = $this->service->buildDirectLink($link);

            expect($url)->toContain('utm_source=affiliate');
            expect($url)->toContain('utm_medium=banner');
        });
    });

    describe('generateTrackingUrl', function (): void {
        test('generates signed tracking URL', function (): void {
            config(['affiliate-network.links.parameter' => 'anl']);
            URL::defaults(['signature' => 'test']);

            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['code' => 'trackcode']);

            $url = $this->service->generateTrackingUrl($link);

            expect($url)->toContain('signature=');
            expect($url)->toContain('expires=');
            expect($url)->toContain('anl=trackcode');
            expect($url)->not->toContain('sig=');
        });
    });

    describe('resolveLink', function (): void {
        test('resolves active link by code', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->active()
                ->create(['code' => 'findme123']);

            $resolved = $this->service->resolveLink('findme123');

            expect($resolved)->not->toBeNull();
            expect($resolved->id)->toBe($link->id);
        });

        test('does not resolve inactive link', function (): void {
            AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->inactive()
                ->create(['code' => 'inactive123']);

            $resolved = $this->service->resolveLink('inactive123');

            expect($resolved)->toBeNull();
        });

        test('returns null for non-existent code', function (): void {
            $resolved = $this->service->resolveLink('nonexistent');

            expect($resolved)->toBeNull();
        });

        test('eager loads relationships', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->forSite($this->site)
                ->create(['code' => 'eager123']);

            $resolved = $this->service->resolveLink('eager123');

            expect($resolved->relationLoaded('offer'))->toBeTrue();
            expect($resolved->relationLoaded('affiliate'))->toBeTrue();
            expect($resolved->relationLoaded('site'))->toBeTrue();
        });
    });

    describe('recordClick', function (): void {
        test('increments click count', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['clicks' => 10]);

            $this->service->recordClick($link);

            expect($link->fresh()->clicks)->toBe(11);
        });

        test('increments from zero', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['clicks' => 0]);

            $this->service->recordClick($link);

            expect($link->fresh()->clicks)->toBe(1);
        });
    });

    describe('recordConversion', function (): void {
        test('increments conversion and revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'conversions' => 5,
                    'revenue' => 10000,
                ]);

            $this->service->recordConversion($link, 5000);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(6);
            expect($fresh->revenue)->toBe(15000);
        });

        test('records conversion without revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['conversions' => 0, 'revenue' => 0]);

            $this->service->recordConversion($link, 0);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(1);
            expect($fresh->revenue)->toBe(0);
        });
    });

    describe('getStats', function (): void {
        test('calculates stats correctly', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
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
                ->forAffiliate($this->affiliate)
                ->create(['clicks' => 0, 'conversions' => 0, 'revenue' => 0]);

            $stats = $this->service->getStats($link);

            expect($stats['conversion_rate'])->toBe(0.0);
            expect($stats['revenue_per_click'])->toBe(0.0);
        });

        test('handles high conversion rate', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->withStats(10, 8, 80000)
                ->create();

            $stats = $this->service->getStats($link);

            expect($stats['conversion_rate'])->toBe(80.0);
        });
    });
});
