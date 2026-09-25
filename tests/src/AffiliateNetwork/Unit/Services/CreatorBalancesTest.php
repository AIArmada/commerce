<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\CreatorBalances;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;

function balanceLink(string $affiliateId): AffiliateOfferLink
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'bal-' . uniqid() . '.example']);
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'landing_url' => 'https://bal.example/landing',
        'requires_approval' => false,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);

    return AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId($affiliateId)->create();
}

describe('CreatorBalances', function (): void {
    test('balances sum posted payouts per currency', function (): void {
        $books = app(NetworkBooks::class);
        $link = balanceLink('bal-aff-1');

        $books->post($link, 10000, 'MYR', 'BAL-1');
        $books->post($link, 20000, 'MYR', 'BAL-2');
        $books->post($link, 5000, 'USD', 'BAL-3');

        expect(app(CreatorBalances::class)->for('bal-aff-1'))->toBe(['MYR' => 3000, 'USD' => 500])
            ->and(app(CreatorBalances::class)->for('bal-nobody'))->toBe([]);
    });

    test('non-posted legs never count', function (): void {
        $books = app(NetworkBooks::class);
        $link = balanceLink('bal-aff-2');

        $posted = $books->post($link, 10000, 'MYR', 'BAL-P');
        $books->post($link, 10000, 'MYR', 'BAL-PROV', LegStatus::Provisional);
        $loser = $books->post($link, 10000, 'MYR', 'BAL-SUP', LegStatus::Provisional);
        $books->supersede($loser, 'engine_won');
        $books->reverse($posted, 'refund');

        // Reversed original + companion both excluded; provisional and
        // superseded never counted: no payable balance remains.
        expect(app(CreatorBalances::class)->for('bal-aff-2'))->toBe([]);
    });
});
