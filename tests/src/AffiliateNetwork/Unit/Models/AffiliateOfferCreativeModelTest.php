<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;

describe('AffiliateOfferCreative Model', function (): void {
    beforeEach(function (): void {
        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->forSite($this->site)->create();
    });

    describe('basic operations', function (): void {
        test('can create creative', function (): void {
            $creative = AffiliateOfferCreative::factory()->forOffer($this->offer)->create([
                'name' => 'Banner 728x90',
            ]);

            expect($creative->id)->not->toBeEmpty();
            expect($creative->name)->toBe('Banner 728x90');
            expect($creative->offer_id)->toBe($this->offer->id);
        });

        test('uses uuid primary key', function (): void {
            $creative = AffiliateOfferCreative::factory()->forOffer($this->offer)->create();

            expect($creative->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

        test('table name comes from config', function (): void {
            $creative = new AffiliateOfferCreative;

            expect($creative->getTable())->toBe('affiliate_network_offer_creatives');
        });
    });

    describe('creative types', function (): void {
        test('creates banner creative', function (): void {
            $creative = AffiliateOfferCreative::factory()->banner(300, 250)->forOffer($this->offer)->create();

            expect($creative->type)->toBe(AffiliateOfferCreative::TYPE_BANNER);
            expect($creative->width)->toBe(300);
            expect($creative->height)->toBe(250);
        });

        test('creates text creative', function (): void {
            $creative = AffiliateOfferCreative::factory()->text()->forOffer($this->offer)->create();

            expect($creative->type)->toBe(AffiliateOfferCreative::TYPE_TEXT);
            expect($creative->width)->toBeNull();
            expect($creative->height)->toBeNull();
        });

        test('creates email creative', function (): void {
            $creative = AffiliateOfferCreative::factory()->email()->forOffer($this->offer)->create();

            expect($creative->type)->toBe(AffiliateOfferCreative::TYPE_EMAIL);
            expect($creative->html_code)->not->toBeNull();
        });

        test('creates html creative', function (): void {
            $creative = AffiliateOfferCreative::factory()->html()->forOffer($this->offer)->create();

            expect($creative->type)->toBe(AffiliateOfferCreative::TYPE_HTML);
            expect($creative->html_code)->not->toBeNull();
        });

        test('creates video creative', function (): void {
            $creative = AffiliateOfferCreative::factory()->video()->forOffer($this->offer)->create();

            expect($creative->type)->toBe(AffiliateOfferCreative::TYPE_VIDEO);
            expect($creative->width)->toBe(1920);
            expect($creative->height)->toBe(1080);
        });
    });

    describe('relationships', function (): void {
        test('belongs to offer', function (): void {
            $creative = AffiliateOfferCreative::factory()->forOffer($this->offer)->create();

            expect($creative->offer)->toBeInstanceOf(AffiliateOffer::class);
            expect($creative->offer->id)->toBe($this->offer->id);
        });
    });

    describe('casts', function (): void {
        test('width and height are integers', function (): void {
            $creative = AffiliateOfferCreative::factory()->banner(728, 90)->forOffer($this->offer)->create();

            expect($creative->width)->toBeInt();
            expect($creative->height)->toBeInt();
        });

        test('is_active is boolean', function (): void {
            $creative = AffiliateOfferCreative::factory()->active()->forOffer($this->offer)->create();

            expect($creative->is_active)->toBeTrue();
            expect($creative->is_active)->toBeBool();
        });

        test('metadata is array', function (): void {
            $creative = AffiliateOfferCreative::factory()->forOffer($this->offer)->create([
                'metadata' => ['tags' => ['sale', 'promo']],
            ]);

            expect($creative->metadata)->toBe(['tags' => ['sale', 'promo']]);
        });
    });
});
