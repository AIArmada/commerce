<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\CreatorBalancesQuery;

describe('CreatorBalancesQuery', function (): void {
    test('balances sum posted payouts per currency', function (): void {
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-1',
            'status' => LegStatus::Posted,
            'commission_currency' => 'MYR',
            'payout_minor' => 1000,
        ]);
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-1',
            'status' => LegStatus::Posted,
            'commission_currency' => 'MYR',
            'payout_minor' => 2000,
        ]);
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-1',
            'status' => LegStatus::Posted,
            'commission_currency' => 'USD',
            'payout_minor' => 5000,
        ]);

        $balances = app(CreatorBalancesQuery::class)->for('bal-aff-1');

        expect($balances)->toHaveKey('MYR')
            ->and($balances['MYR']->payableMinor)->toBe(3000)
            ->and($balances)->toHaveKey('USD')
            ->and($balances['USD']->payableMinor)->toBe(5000)
            ->and(app(CreatorBalancesQuery::class)->for('bal-nobody'))->toBe([]);
    });

    test('only pending and posted legs contribute to balances', function (): void {
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-2',
            'status' => LegStatus::Pending,
            'commission_currency' => 'MYR',
            'payout_minor' => 700,
        ]);
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-2',
            'status' => LegStatus::Provisional,
            'commission_currency' => 'MYR',
            'payout_minor' => 900,
        ]);
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-2',
            'status' => LegStatus::Superseded,
            'commission_currency' => 'MYR',
            'payout_minor' => 900,
        ]);
        NetworkConversionLeg::factory()->create([
            'affiliate_id' => 'bal-aff-2',
            'status' => LegStatus::Posted,
            'commission_currency' => 'MYR',
            'payout_minor' => 1000,
        ]);

        $balance = app(CreatorBalancesQuery::class)->for('bal-aff-2')['MYR'];

        expect($balance->pendingMinor)->toBe(700)
            ->and($balance->payableMinor)->toBe(1000)
            ->and($balance->paidMinor)->toBe(0)
            ->and($balance->reversedMinor)->toBe(0);
    });
});
