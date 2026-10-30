<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\AllocateUplineCommissions;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Actions\Payouts\ClaimScheduledPayout;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\Services\PerformanceBonusService;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\Affiliates\Services\UplineService;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;

test('commercial purpose scope includes unmarked and general profiles only', function (): void {
    $plain = createTestAffiliate(['code' => 'PURP-PLAIN']);
    $general = createTestAffiliate(['code' => 'PURP-GENERAL', 'purpose' => Affiliate::PURPOSE_GENERAL]);
    $mixedCaseGeneral = createTestAffiliate(['code' => 'PURP-GENERAL-MIXED', 'purpose' => 'GeNeRaL ']);
    $tagged = createTestAffiliate(['code' => 'PURP-TAGGED', 'purpose' => 'host_attribution_only']);

    $ids = Affiliate::query()->whereCommercialPurpose()->pluck('id')->map(fn ($id): string => (string) $id)->all();

    expect($ids)->toContain((string) $plain->getKey())
        ->and($ids)->toContain((string) $general->getKey())
        ->and($ids)->toContain((string) $mixedCaseGeneral->getKey())
        ->and($ids)->not->toContain((string) $tagged->getKey());

    expect($plain->isCommercialPurpose())->toBeTrue()
        ->and($general->isCommercialPurpose())->toBeTrue()
        ->and($mixedCaseGeneral->isCommercialPurpose())->toBeTrue()
        ->and($tagged->isCommercialPurpose())->toBeFalse();
});

test('direct rank operations cannot assign ranks to purpose-tagged affiliates', function (): void {
    $tagged = createTestAffiliate(['code' => 'PURP-RANK', 'purpose' => 'host_attribution_only']);
    $rank = AffiliateRank::create([
        'name' => 'Purpose Rank',
        'slug' => 'purpose-rank',
        'level' => 1,
        'min_personal_sales' => 0,
        'min_team_sales' => 0,
        'commission_rate_basis_points' => 0,
        'min_active_downlines' => 0,
        'currency' => 'USD',
    ]);
    $service = app(RankQualificationService::class);

    expect($service->evaluate($tagged))->toBeNull();
    $service->processRankChange($tagged);

    expect(fn () => $service->assignRank($tagged, $rank))
        ->toThrow(InvalidArgumentException::class);
    expect($tagged->fresh()->rank_id)->toBeNull();

    $alreadyRanked = createTestAffiliate(['code' => 'PURP-RANK-KEEP']);
    $service->assignRank($alreadyRanked, $rank);
    $alreadyRanked->forceFill(['purpose' => 'host_attribution_only'])->save();
    $service->processRankChange($alreadyRanked->refresh());

    expect($alreadyRanked->fresh()->rank_id)->toBe($rank->getKey());
});

test('upline operations exclude tagged affiliates and reject direct enrollment', function (): void {
    config(['affiliates.upline.enabled' => true]);

    $service = app(UplineService::class);
    $root = createTestAffiliate(['code' => 'PURP-UPROOT']);
    $commercial = createTestAffiliate(['code' => 'PURP-UPCHILD']);
    $tagged = createTestAffiliate(['code' => 'PURP-UPTAGGED', 'purpose' => 'host_attribution_only']);

    $service->addToUpline($root);
    $service->addToUpline($commercial, $root);

    expect(fn () => $service->addToUpline($tagged, $root))
        ->toThrow(InvalidArgumentException::class);

    AffiliateUpline::query()->create([
        'ancestor_id' => $root->getKey(),
        'descendant_id' => $tagged->getKey(),
        'depth' => 1,
    ]);

    expect($service->getActiveDownlineCount($root))->toBe(1)
        ->and(AffiliateUpline::getDescendantCount($root))->toBe(1)
        ->and($service->getTeamSales($tagged))->toBe(0);
});

test('scheduled payout claims refuse purpose-tagged affiliates', function (): void {
    $tagged = createTestAffiliate(['code' => 'PURP-CLAIM', 'purpose' => 'host_reward_recipient']);

    AffiliateBalance::query()->create([
        'affiliate_id' => $tagged->getKey(),
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 50000,
        'lifetime_earnings_minor' => 50000,
        'minimum_payout_minor' => 0,
    ]);

    $claim = app(ClaimScheduledPayout::class);

    expect($claim->isEligibleSnapshot((string) $tagged->getKey(), 0, 'USD'))->toBeFalse()
        ->and($claim->handle((string) $tagged->getKey(), 0, 'USD'))->toBeNull();
});

test('manual payout creation refuses approved conversions for purpose-tagged affiliates', function (): void {
    $tagged = createTestAffiliate(['code' => 'PURP-PAYOUT', 'purpose' => 'share_member']);
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $tagged->getKey(),
        'affiliate_code' => $tagged->code,
        'external_reference' => 'PURP-PAYOUT-' . Str::uuid(),
        'subject_key' => 'ticket:' . Str::uuid(),
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now(),
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'currency' => 'USD',
        'commission_currency' => 'USD',
    ]);

    expect(fn () => CreatePayout::run([(string) $conversion->getKey()]))
        ->toThrow(InvalidArgumentException::class, 'Only commercial-purpose');
});

test('reward-award backings remain payable through the separate award settlement flow', function (): void {
    config(['affiliates.payouts.reward_award_settlement.enabled' => true]);

    $recipient = createTestAffiliate(['code' => 'PURP-HOST-REWARD-PAYOUT', 'purpose' => 'host_reward_recipient']);
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $recipient->getKey(),
        'affiliate_code' => $recipient->code,
        'external_reference' => 'PURP-HOST-REWARD-PAYOUT-' . Str::uuid(),
        'conversion_type' => 'reward_award',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now(),
        'subtotal_minor' => 0,
        'value_minor' => 0,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);

    AffiliateBalance::query()->create([
        'affiliate_id' => $recipient->getKey(),
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 1000,
        'lifetime_earnings_minor' => 1000,
        'minimum_payout_minor' => 0,
    ]);

    $payout = CreatePayout::run([(string) $conversion->getKey()]);

    expect($payout->conversion_count)->toBe(1)
        ->and($payout->total_minor)->toBe(1000);
});

test('reward-award settlement is blocked when the explicit switch is off', function (): void {
    config(['affiliates.payouts.reward_award_settlement.enabled' => false]);

    $recipient = createTestAffiliate(['code' => 'PURP-HOST-REWARD-PAYOUT-OFF', 'purpose' => 'host_reward_recipient']);
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $recipient->getKey(),
        'affiliate_code' => $recipient->code,
        'external_reference' => 'PURP-HOST-REWARD-PAYOUT-OFF-' . Str::uuid(),
        'conversion_type' => 'reward_award',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now(),
        'subtotal_minor' => 0,
        'value_minor' => 0,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);

    expect(fn () => CreatePayout::run([(string) $conversion->getKey()]))
        ->toThrow(InvalidArgumentException::class, 'Only commercial-purpose');
});

test('reward-award settlement switch never permits mixed purchase payouts', function (): void {
    config(['affiliates.payouts.reward_award_settlement.enabled' => true]);

    $recipient = createTestAffiliate(['code' => 'PURP-HOST-REWARD-MIXED', 'purpose' => 'host_reward_recipient']);
    $conversions = collect(['reward_award', 'purchase'])->map(fn (string $type): AffiliateConversion => AffiliateConversion::create([
        'affiliate_id' => $recipient->getKey(),
        'affiliate_code' => $recipient->code,
        'external_reference' => 'PURP-HOST-REWARD-MIXED-' . $type . '-' . Str::uuid(),
        'conversion_type' => $type,
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now(),
        'subtotal_minor' => 1000,
        'total_minor' => 1000,
        'value_minor' => 1000,
        'commission_minor' => 100,
        'commission_currency' => 'USD',
    ]));

    expect(fn () => CreatePayout::run($conversions->map(fn (AffiliateConversion $conversion): string => (string) $conversion->getKey())->all()))
        ->toThrow(InvalidArgumentException::class, 'Only commercial-purpose');
});

test('performance bonus awards skip purpose-tagged affiliates', function (): void {
    $commercial = createTestAffiliate(['code' => 'PURP-BONUS-OK']);
    $tagged = createTestAffiliate(['code' => 'PURP-BONUS-SKIP', 'purpose' => 'host_attribution_only']);

    $bonuses = [
        [
            'affiliate_id' => (string) $commercial->getKey(),
            'bonus_type' => 'top_performer',
            'amount_minor' => 1000,
            'reason' => 'purpose exclusion test',
            'metrics' => ['period' => '2026-10'],
        ],
        [
            'affiliate_id' => (string) $tagged->getKey(),
            'bonus_type' => 'top_performer',
            'amount_minor' => 1000,
            'reason' => 'purpose exclusion test',
            'metrics' => ['period' => '2026-10'],
        ],
    ];

    $awarded = app(PerformanceBonusService::class)->awardBonuses($bonuses);

    expect($awarded)->toBe(1)
        ->and(AffiliateConversion::query()->where('affiliate_id', $commercial->getKey())->whereNotNull('performance_bonus_key')->exists())->toBeTrue()
        ->and(AffiliateConversion::query()->where('affiliate_id', $tagged->getKey())->whereNotNull('performance_bonus_key')->exists())->toBeFalse();
});

test('conversion maturity skips purpose-tagged affiliates', function (): void {
    config(['affiliates.payouts.maturity_days' => 30]);

    $tagged = createTestAffiliate(['code' => 'PURP-MATURE', 'purpose' => 'host_attribution_only']);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $tagged->getKey(),
        'affiliate_code' => $tagged->code,
        'external_reference' => 'PURP-MAT-' . uniqid(),
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => QualifiedConversion::class,
        'occurred_at' => CarbonImmutable::now()->subDays(60),
    ]);

    $matured = app(ProcessConversionMaturity::class)->handle();

    expect($matured)->toBe(0)
        ->and($conversion->fresh()->status->getValue())->toBe(QualifiedConversion::value());
});

test('upline allocation skips purpose-tagged earners and ancestors', function (): void {
    config([
        'affiliates.payouts.multi_level.enabled' => true,
        'affiliates.payouts.multi_level.levels' => [0.1],
        'affiliates.events.dispatch_conversion' => false,
        'affiliates.events.dispatch_webhooks' => false,
    ]);

    $taggedEarner = createTestAffiliate(['code' => 'PURP-UPLINE-EARNER', 'purpose' => 'host_attribution_only']);
    $commercialParent = createTestAffiliate(['code' => 'PURP-UPLINE-PARENT']);
    $taggedEarner->forceFill(['parent_affiliate_id' => $commercialParent->getKey()])->save();

    $action = new AllocateUplineCommissions(
        app(Dispatcher::class),
        app(WebhookDispatcher::class),
        app(ApplyConversionAccounting::class),
    );

    $status = ConversionStatus::fromString(PendingConversion::class);

    $action->handle([new AffiliateConversionData(
        id: (string) Str::uuid(),
        affiliateId: (string) $taggedEarner->getKey(),
        affiliateCode: $taggedEarner->code,
        uplineLevels: [['level' => 1, 'share' => 0.05]],
        commissionMinor: 1000,
        commissionCurrency: 'USD',
    )], false, $status, null);

    expect(AffiliateConversion::query()->where('channel', 'upline')->exists())->toBeFalse();

    // A tagged ancestor earns nothing while the commercial grandparent still can.
    $grandparent = createTestAffiliate(['code' => 'PURP-UPLINE-GP']);
    $taggedParent = createTestAffiliate(['code' => 'PURP-UPLINE-TP', 'purpose' => 'host_attribution_only', 'parent_affiliate_id' => $grandparent->getKey()]);
    $child = createTestAffiliate(['code' => 'PURP-UPLINE-CHILD', 'parent_affiliate_id' => $taggedParent->getKey()]);

    config(['affiliates.payouts.multi_level.levels' => [0.1, 0.05]]);

    $action->handle([new AffiliateConversionData(
        id: (string) Str::uuid(),
        affiliateId: (string) $child->getKey(),
        affiliateCode: $child->code,
        uplineLevels: [['level' => 1, 'share' => 0.05], ['level' => 2, 'share' => 0.05]],
        commissionMinor: 1000,
        commissionCurrency: 'USD',
    )], false, $status, null);

    expect(AffiliateConversion::query()->where('affiliate_id', $taggedParent->getKey())->where('channel', 'upline')->exists())->toBeFalse()
        ->and(AffiliateConversion::query()->where('affiliate_id', $grandparent->getKey())->where('channel', 'upline')->exists())->toBeTrue();
});

test('leaderboard excludes purpose-tagged affiliates', function (): void {
    $tagged = createTestAffiliate(['code' => 'PURP-LEAD', 'purpose' => 'host_attribution_only']);

    AffiliateConversion::create([
        'affiliate_id' => $tagged->getKey(),
        'affiliate_code' => $tagged->code,
        'external_reference' => 'PURP-LEAD-' . uniqid(),
        'value_minor' => 99999999,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now(),
    ]);

    $rows = app(PerformanceBonusService::class)->getLeaderboard(
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    );

    $ids = $rows->map(fn ($row): string => (string) $row['affiliate_id'])->all();

    expect($ids)->not->toContain((string) $tagged->getKey());
});
