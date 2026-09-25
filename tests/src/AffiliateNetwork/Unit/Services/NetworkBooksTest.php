<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;

function booksFixtures(array $offerAttributes = []): AffiliateOfferLink
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'books-' . uniqid() . '.example']);

    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'landing_url' => 'https://books.example/landing',
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ], $offerAttributes));

    return AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('books-aff-1')->create();
}

describe('NetworkBooks', function (): void {
    test('posting is idempotent on link and reference', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);

        $first = $books->post($link, 89900, 'MYR', 'BOOKS-1');
        $second = $books->post($link, 89900, 'MYR', 'BOOKS-1');

        expect($second->getKey())->toBe($first->getKey())
            ->and(NetworkConversionLeg::query()->count())->toBe(1);
    });

    test('fixed rate wins over tiers and base', function (): void {
        $link = booksFixtures([
            'rate_fixed_minor' => 2500,
            'volume_tiers' => [['min_volume_minor' => 0, 'rate_bp' => 5000, 'currency' => 'MYR']],
        ]);

        $leg = app(NetworkBooks::class)->post($link, 99999, 'MYR', 'BOOKS-FIXED');

        expect($leg->commission_minor)->toBe(2500)
            ->and($leg->tier_rate_bp)->toBeNull();
    });

    test('volume tiers apply on cumulative affiliate revenue', function (): void {
        $link = booksFixtures([
            'rate_base_bp' => 1000,
            'volume_tiers' => [
                ['min_volume_minor' => 0, 'rate_bp' => 1000, 'currency' => 'MYR'],
                ['min_volume_minor' => 100000, 'rate_bp' => 2000, 'currency' => 'MYR'],
            ],
        ]);
        $books = app(NetworkBooks::class);

        $first = $books->post($link, 60000, 'MYR', 'BOOKS-T1');
        $second = $books->post($link, 60000, 'MYR', 'BOOKS-T2');

        expect($first->commission_minor)->toBe(6000)
            ->and($first->tier_rate_bp)->toBe(1000)
            ->and($second->commission_minor)->toBe(12000)
            ->and($second->tier_rate_bp)->toBe(2000)
            ->and($second->tier_min_volume_minor)->toBe(100000);
    });

    test('tiers ignore other affiliates and currencies', function (): void {
        $link = booksFixtures([
            'volume_tiers' => [['min_volume_minor' => 100000, 'rate_bp' => 5000, 'currency' => 'MYR']],
        ]);

        $other = AffiliateOfferLink::factory()
            ->forOffer($link->offer)
            ->forAffiliateId('books-aff-2')
            ->create();

        app(NetworkBooks::class)->post($other, 500000, 'MYR', 'BOOKS-TX');

        $leg = app(NetworkBooks::class)->post($link, 60000, 'USD', 'BOOKS-T3');

        expect($leg->commission_minor)->toBe(6000)
            ->and($leg->tier_rate_bp)->toBeNull();
    });

    test('network fee splits commission into payout and fee', function (): void {
        $link = booksFixtures(['rate_base_bp' => 1000, 'network_fee_bp' => 2000]);

        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'BOOKS-FEE');

        expect($leg->commission_minor)->toBe(1000)
            ->and($leg->fee_minor)->toBe(200)
            ->and($leg->fee_bp)->toBe(2000)
            ->and($leg->payout_minor)->toBe(800);
    });

    test('fee defaults from config and clamps to commission', function (): void {
        config(['affiliate-network.fees.default_bp' => 10000]);
        $link = booksFixtures(['rate_base_bp' => 1000]);

        $leg = app(NetworkBooks::class)->post($link, 10000, 'MYR', 'BOOKS-FEEMAX');

        expect($leg->fee_minor)->toBe(1000)
            ->and($leg->payout_minor)->toBe(0);
    });

    test('legs keep real money on currency mismatch', function (): void {
        $link = booksFixtures();
        $link->update(['currency' => 'USD']);

        $leg = app(NetworkBooks::class)->post($link, 12345, 'MYR', 'BOOKS-FX');

        expect($leg->revenue_minor)->toBe(12345)
            ->and($leg->revenue_currency)->toBe('MYR');
    });

    test('confirm and supersede finalize provisional legs once', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);

        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-PROV', LegStatus::Provisional);
        $confirmed = $books->confirm($leg);
        $again = $books->confirm($confirmed);

        expect($confirmed->status)->toBe(LegStatus::Posted)
            ->and($again->getKey())->toBe($confirmed->getKey());

        $loser = $books->post($link, 10000, 'MYR', 'BOOKS-LOSE', LegStatus::Provisional);
        $superseded = $books->supersede($loser, 'engine_won');

        expect($superseded->status)->toBe(LegStatus::Superseded)
            ->and($superseded->metadata['superseded_reason'])->toBe('engine_won');
    });

    test('reverse marks the leg and posts a negated companion', function (): void {
        $link = booksFixtures(['network_fee_bp' => 2000]);
        $books = app(NetworkBooks::class);
        $leg = $books->post($link, 10000, 'MYR', 'BOOKS-REV');

        $companion = $books->reverse($leg, 'chargeback');

        expect($companion->commission_minor)->toBe(-1000)
            ->and($companion->fee_minor)->toBe(-200)
            ->and($companion->payout_minor)->toBe(-800)
            ->and($companion->status)->toBe(LegStatus::Reversed)
            ->and($leg->fresh()->status)->toBe(LegStatus::Reversed);
    });

    test('recount rebuilds counters from legs', function (): void {
        $link = booksFixtures();
        $books = app(NetworkBooks::class);
        $books->post($link, 10000, 'MYR', 'BOOKS-RC1');
        $books->post($link, 5000, 'MYR', 'BOOKS-RC2');

        $link->forceFill(['conversions' => 99, 'revenue' => 1])->save();

        $recount = $books->recountLinkCounters($link->fresh());

        expect($recount)->toBe(['conversions' => 2, 'revenue_minor' => 15000])
            ->and($link->fresh()->conversions)->toBe(2)
            ->and($link->fresh()->revenue)->toBe(15000);
    });
});
