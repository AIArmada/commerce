<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Settings\AffiliateBonusSettings;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');

    $this->affiliate = Affiliate::create([
        'code' => 'BONUSCMD-' . uniqid(),
        'name' => 'Bonus Command Affiliate',
        'contact_email' => 'bonuscmd@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'order_reference' => 'BONUSCMD-ORDER-' . uniqid(),
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->startOfMonth()->addDay(),
    ]);

    AffiliateBonusSettings::fake([
        'topPerformerEnabled' => true,
        'topPerformerMinRevenue' => 0,
        'topPerformerMinRevenueCurrency' => 'USD',
        'topPerformerPositions' => [1 => 1000],
        'recruitmentEnabled' => false,
        'consistencyEnabled' => false,
        'growthEnabled' => false,
    ]);
});

function bonusAwardCount(): int
{
    return AffiliateConversion::query()->whereNotNull('performance_bonus_key')->count();
}

describe('AwardPerformanceBonusesCommand', function (): void {
    test('dry run calculates without writing', function (): void {
        $exit = Artisan::call('affiliates:award-bonuses', ['--dry-run' => true]);

        expect($exit)->toBe(0)
            ->and(bonusAwardCount())->toBe(0)
            ->and(Artisan::output())->toContain('Calculated: 1');
    });

    test('awards calculated bonuses exactly once', function (): void {
        expect(Artisan::call('affiliates:award-bonuses'))->toBe(0)
            ->and(bonusAwardCount())->toBe(1);

        expect(Artisan::call('affiliates:award-bonuses'))->toBe(0)
            ->and(bonusAwardCount())->toBe(1)
            ->and(Artisan::output())->toContain('Awarded: 0');
    });

    test('type filter restricts calculation', function (): void {
        $exit = Artisan::call('affiliates:award-bonuses', ['--dry-run' => true, '--type' => 'recruitment']);

        expect($exit)->toBe(0)
            ->and(Artisan::output())->toContain('Calculated: 0');
    });

    test('rejects an invalid month', function (): void {
        expect(Artisan::call('affiliates:award-bonuses', ['--month' => '2026-13']))->toBe(1)
            ->and(bonusAwardCount())->toBe(0);
    });

    test('rejects an invalid type', function (): void {
        expect(Artisan::call('affiliates:award-bonuses', ['--type' => 'product']))->toBe(1)
            ->and(Artisan::call('affiliates:award-bonuses', ['--type' => 'nope']))->toBe(1)
            ->and(bonusAwardCount())->toBe(0);
    });
});
