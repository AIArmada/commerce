<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use Carbon\CarbonImmutable;

describe('AffiliateOfferLink Model', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->forSite($this->site)->create();
        $this->affiliate = Affiliate::create([
            'code' => 'AFF' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    describe('basic operations', function (): void {
        test('can create link', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'target_url' => 'https://example.com/product',
                ]);

            expect($link->id)->not->toBeEmpty();
            expect($link->offer_id)->toBe($this->offer->id);
            expect($link->affiliate_id)->toBe($this->affiliate->id);
            expect($link->target_url)->toBe('https://example.com/product');
        });

        test('uses uuid primary key', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($link->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

        test('table name comes from config', function (): void {
            $link = new AffiliateOfferLink;

            expect($link->getTable())->toBe('affiliate_network_offer_links');
        });
    });

    describe('code generation', function (): void {
        test('auto generates code on creation', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['code' => '']);

            expect($link->code)->not->toBeEmpty();
            expect(mb_strlen($link->code))->toBe(16);
        });

        test('generateCode creates 16 character hex string', function (): void {
            $code = AffiliateOfferLink::generateCode();

            expect($code)->toMatch('/^[0-9a-f]{16}$/');
        });

        test('uses provided code when set', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['code' => 'customcode12345!']);

            expect($link->code)->toBe('customcode12345!');
        });
    });

    describe('click tracking', function (): void {
        test('incrementClicks increases click count', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['clicks' => 10]);

            $link->incrementClicks();

            expect($link->fresh()->clicks)->toBe(11);
        });

        test('incrementClicks works from zero', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['clicks' => 0]);

            $link->incrementClicks();

            expect($link->fresh()->clicks)->toBe(1);
        });
    });

    describe('conversion tracking', function (): void {
        test('recordConversion increases conversions and revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->withStats(100, 5, 25000)
                ->create();

            $link->recordConversion(5000);

            $fresh = $link->fresh();
            expect($fresh->conversions)->toBe(6);
            expect($fresh->revenue)->toBe(30000);
        });
    });

    describe('expiration', function (): void {
        test('isExpired returns true when expires_at is in past', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->expired()
                ->create();

            expect($link->isExpired())->toBeTrue();
        });

        test('isExpired returns false when expires_at is in future', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->expiresAt(now()->addDays(30))
                ->create();

            expect($link->isExpired())->toBeFalse();
        });

        test('isExpired returns false when expires_at is null', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create(['expires_at' => null]);

            expect($link->isExpired())->toBeFalse();
        });
    });

    describe('relationships', function (): void {
        test('belongs to offer', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($link->offer)->toBeInstanceOf(AffiliateOffer::class);
            expect($link->offer->id)->toBe($this->offer->id);
        });

        test('belongs to affiliate', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create();

            expect($link->affiliate)->toBeInstanceOf(Affiliate::class);
            expect($link->affiliate->id)->toBe($this->affiliate->id);
        });

        test('belongs to site', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->forSite($this->site)
                ->create();

            expect($link->site)->toBeInstanceOf(AffiliateSite::class);
            expect($link->site->id)->toBe($this->site->id);
        });
    });

    describe('casts', function (): void {
        test('clicks conversions revenue are integers', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->withStats(100, 10, 50000)
                ->create();

            expect($link->clicks)->toBeInt();
            expect($link->conversions)->toBeInt();
            expect($link->revenue)->toBeInt();
        });

        test('is_active is boolean', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->active()
                ->create();

            expect($link->is_active)->toBeTrue();
            expect($link->is_active)->toBeBool();
        });

        test('expires_at is immutable datetime', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->expiresAt(now()->addDays(30))
                ->create();

            expect($link->expires_at)->toBeInstanceOf(CarbonImmutable::class);
        });

        test('metadata is array', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliate($this->affiliate)
                ->create([
                    'metadata' => ['campaign' => 'summer_sale'],
                ]);

            expect($link->metadata)->toBe(['campaign' => 'summer_sale']);
        });
    });
});
