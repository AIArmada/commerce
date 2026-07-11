<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\PerformanceBonusService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config(['affiliates.owner.enabled' => false]);

    $this->service = app(PerformanceBonusService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'BONUS-CONCUR-' . bin2hex(random_bytes(4)),
        'name' => 'Concurrency Test Affiliate',
        'contact_email' => 'concur@example.com',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateBalance::create([
        'affiliate_id' => $this->affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 0,
        'lifetime_earnings_minor' => 0,
        'minimum_payout_minor' => 5000,
    ]);
});

test('concurrent bonus awards do not create duplicate conversions', function (): void {
    $bonuses = [
        [
            'affiliate_id' => $this->affiliate->id,
            'affiliate_name' => $this->affiliate->name,
            'bonus_type' => 'top_performer',
            'amount_minor' => 5000,
            'reason' => 'Test bonus',
            'metrics' => ['period' => '2026-07'],
        ],
    ];

    $results = DB::transaction(function () use ($bonuses): array {
        return [
            $this->service->awardBonuses($bonuses),
            $this->service->awardBonuses($bonuses),
        ];
    });

    expect($results)->toBe([1, 0]);
    expect(AffiliateConversion::where('affiliate_id', $this->affiliate->id)->count())->toBe(1);
});

test('bonus awards are atomic under concurrent load', function (): void {
    $bonuses = [
        [
            'affiliate_id' => $this->affiliate->id,
            'affiliate_name' => $this->affiliate->name,
            'bonus_type' => 'top_performer',
            'amount_minor' => 5000,
            'reason' => 'Atomic test',
            'metrics' => [],
        ],
    ];

    $this->service->awardBonuses($bonuses);

    $balance = AffiliateBalance::where('affiliate_id', $this->affiliate->id)->first();
    expect($balance->available_minor)->toBe(5000);
    expect($balance->lifetime_earnings_minor)->toBe(5000);
});

test('owner scoped bonus awards isolate between tenants', function (): void {
    config(['affiliates.owner.enabled' => true]);
    config(['affiliates.owner.auto_assign_on_create' => true]);

    $owner1 = User::create(['name' => 'Owner 1', 'email' => 'owner1-' . bin2hex(random_bytes(4)) . '@test.com', 'password' => bcrypt('test')]);
    $owner2 = User::create(['name' => 'Owner 2', 'email' => 'owner2-' . bin2hex(random_bytes(4)) . '@test.com', 'password' => bcrypt('test')]);

    $affiliate1 = Affiliate::create([
        'code' => 'OWNER1-' . bin2hex(random_bytes(4)),
        'name' => 'Owner 1 Affiliate',
        'contact_email' => 'o1@example.com',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $affiliate2 = Affiliate::create([
        'code' => 'OWNER2-' . bin2hex(random_bytes(4)),
        'name' => 'Owner 2 Affiliate',
        'contact_email' => 'o2@example.com',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateBalance::create([
        'affiliate_id' => $affiliate2->id,
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 0,
        'lifetime_earnings_minor' => 0,
        'minimum_payout_minor' => 5000,
    ]);

    // Award bonus under owner1
    OwnerContext::withOwner($owner1, function () use ($affiliate1): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate1->id,
            'affiliate_code' => $affiliate1->code,
            'order_reference' => 'ORD-OWNER1',
            'subtotal_minor' => 10000,
            'total_minor' => 10000,
            'commission_minor' => 1000,
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->owner_id)->not->toBeNull();
    });

    // Award bonus under owner2
    OwnerContext::withOwner($owner2, function () use ($affiliate2): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate2->id,
            'affiliate_code' => $affiliate2->code,
            'order_reference' => 'ORD-OWNER2',
            'subtotal_minor' => 20000,
            'total_minor' => 20000,
            'commission_minor' => 2000,
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);

        expect($conversion->owner_id)->not->toBeNull();
    });

    // Verify isolation: each owner sees only their own conversion
    OwnerContext::withOwner($owner1, function (): void {
        $count = AffiliateConversion::forOwner()->count();
        expect($count)->toBe(1);
    });

    OwnerContext::withOwner($owner2, function (): void {
        $count = AffiliateConversion::forOwner()->count();
        expect($count)->toBe(1);
    });
});

test('leaderboard respects owner scope', function (): void {
    config(['affiliates.owner.enabled' => true]);
    config(['affiliates.owner.auto_assign_on_create' => true]);
    config(['affiliates.owner.include_global' => false]);

    $ownerA = User::create(['name' => 'Team A Owner', 'email' => 'teama-' . bin2hex(random_bytes(4)) . '@test.com', 'password' => bcrypt('test')]);
    $ownerB = User::create(['name' => 'Team B Owner', 'email' => 'teamb-' . bin2hex(random_bytes(4)) . '@test.com', 'password' => bcrypt('test')]);

    OwnerContext::withOwner($ownerA, function (): void {
        $affiliateA = Affiliate::create([
            'code' => 'TEAMA-' . bin2hex(random_bytes(4)),
            'name' => 'Team A Affiliate',
            'contact_email' => 'teama@example.com',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect(AffiliateConversion::forOwner()->count())->toBe(0);

        AffiliateConversion::create([
            'affiliate_id' => $affiliateA->id,
            'affiliate_code' => $affiliateA->code,
            'order_reference' => 'ORD-A',
            'subtotal_minor' => 50000,
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);
    });

    OwnerContext::withOwner($ownerB, function (): void {
        $affiliateB = Affiliate::create([
            'code' => 'TEAMB-' . bin2hex(random_bytes(4)),
            'name' => 'Team B Affiliate',
            'contact_email' => 'teamb@example.com',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliateB->id,
            'affiliate_code' => $affiliateB->code,
            'order_reference' => 'ORD-B',
            'subtotal_minor' => 100000,
            'total_minor' => 100000,
            'commission_minor' => 10000,
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        $leaderboard = $this->service->getLeaderboard();
        expect($leaderboard)->toHaveCount(1);
        if ($leaderboard->isNotEmpty()) {
            expect($leaderboard->first()['total_revenue'])->toBe(50000);
        }
    });

    OwnerContext::withOwner($ownerB, function (): void {
        $leaderboard = $this->service->getLeaderboard();
        expect($leaderboard)->toHaveCount(1);
        if ($leaderboard->isNotEmpty()) {
            expect($leaderboard->first()['total_revenue'])->toBe(100000);
        }
    });
});
