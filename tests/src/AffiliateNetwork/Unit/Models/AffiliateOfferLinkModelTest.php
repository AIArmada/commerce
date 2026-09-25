<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Links\Models\Link;
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
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withTarget('https://example.com/product')
                ->create();

            expect($link->id)->not->toBeEmpty();
            expect($link->offer_id)->toBe($this->offer->id);
            expect($link->affiliate_id)->toBe($this->affiliate->id);
            expect($link->link->destination_url)->toBe('https://example.com/product');
        });

        test('uses uuid primary key', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            expect($link->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

    });

    describe('tracked link', function (): void {
        test('factory mints a signed backing link with attribution parameters', function (): void {
            config(['affiliate-network.links.parameter' => 'anl']);

            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withSlug('offer-slug-1')
                ->create();

            expect($link->link)->toBeInstanceOf(Link::class)
                ->and($link->trackedSlug())->toBe('offer-slug-1')
                ->and($link->link->slug)->toBe('offer-slug-1')
                ->and($link->link->require_signature)->toBeTrue()
                ->and($link->link->subject_type)->toBe($link->getMorphClass())
                ->and($link->link->subject_id)->toBe((string) $link->getKey())
                ->and($link->link->parameters['anl'])->toBe('offer-slug-1');
        });
    });

    describe('click tracking', function (): void {
        test('incrementClicks increases click count', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(10, 0, 0)
                ->create();

            $link->incrementClicks();

            expect($link->fresh()->clicks)->toBe(11);
        });

        test('incrementClicks works from zero', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            $link->incrementClicks();

            expect($link->fresh()->clicks)->toBe(1);
        });
    });

    describe('conversion tracking', function (): void {
        test('recordConversion increases conversions and revenue', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
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
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->expired()
                ->create();

            expect($link->isExpired())->toBeTrue();
        });

        test('isExpired returns false when expires_at is in future', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->expiresAt(now()->addDays(30))
                ->create();

            expect($link->isExpired())->toBeFalse();
        });

        test('isExpired returns false when expires_at is null', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            expect($link->isExpired())->toBeFalse();
        });
    });

    describe('relationships', function (): void {
        test('belongs to offer', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            expect($link->offer)->toBeInstanceOf(AffiliateOffer::class);
            expect($link->offer->id)->toBe($this->offer->id);
        });

        test('belongs to affiliate', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            expect($link->affiliate)->toBeInstanceOf(Affiliate::class);
            expect($link->affiliate->id)->toBe($this->affiliate->id);
        });

        test('belongs to site', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->forSite($this->site)
                ->create();

            expect($link->site)->toBeInstanceOf(AffiliateSite::class);
            expect($link->site->id)->toBe($this->site->id);
        });

        test('belongs to tracked link', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create();

            expect($link->link)->toBeInstanceOf(Link::class);
            expect($link->link->id)->toBe($link->link_id);
        });
    });

    describe('casts', function (): void {
        test('clicks conversions revenue are integers', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->withStats(100, 10, 50000)
                ->create();

            expect($link->clicks)->toBeInt();
            expect($link->conversions)->toBeInt();
            expect($link->revenue)->toBeInt();
        });

        test('is_active is boolean', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->active()
                ->create();

            expect($link->is_active)->toBeTrue();
            expect($link->is_active)->toBeBool();
        });

        test('backing link expires_at is immutable datetime', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->expiresAt(now()->addDays(30))
                ->create();

            expect($link->link()->withoutOwnerScope()->first()->expires_at)->toBeInstanceOf(CarbonImmutable::class);
        });

        test('metadata is array', function (): void {
            $link = AffiliateOfferLink::factory()
                ->forOffer($this->offer)
                ->forAffiliateId((string) $this->affiliate->getKey())
                ->create([
                    'metadata' => ['campaign' => 'summer_sale'],
                ]);

            expect($link->metadata)->toBe(['campaign' => 'summer_sale']);
        });
    });
});
