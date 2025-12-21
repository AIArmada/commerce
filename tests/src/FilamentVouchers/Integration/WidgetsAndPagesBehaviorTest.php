<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentVouchers\Pages\ABTestDashboard;
use AIArmada\FilamentVouchers\Widgets\CampaignStatsWidget;
use AIArmada\FilamentVouchers\Widgets\FraudStatsWidget;
use AIArmada\FilamentVouchers\Widgets\GiftCardStatsWidget;
use AIArmada\FilamentVouchers\Widgets\GiftCardTransactionTimelineWidget;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherWalletStatsWidget;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

final class TestOwnerResolverVouchersWidgets implements OwnerResolverInterface
{
    public function __construct(private readonly ?Model $owner) {}

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

it('executes widget logic and enforces owner scoping on voucher usage timelines', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'vouchers-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'vouchers-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new TestOwnerResolverVouchersWidgets($ownerA));

    $voucherA = Voucher::query()->create([
        'code' => 'V-A',
        'name' => 'Voucher A',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $voucherA->assignOwner($ownerA)->save();

    $voucherB = Voucher::query()->create([
        'code' => 'V-B',
        'name' => 'Voucher B',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $voucherB->assignOwner($ownerB)->save();

    VoucherUsage::query()->create([
        'voucher_id' => $voucherA->id,
        'discount_amount' => 500,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
        'user_identifier' => 'u-1',
    ]);

    VoucherWallet::query()->create([
        'voucher_id' => $voucherA->id,
        'holder_type' => $ownerA->getMorphClass(),
        'holder_id' => $ownerA->getKey(),
        'is_claimed' => true,
        'claimed_at' => now(),
        'is_redeemed' => false,
    ]);

    VoucherFraudSignal::query()->create([
        'voucher_id' => $voucherA->id,
        'voucher_code' => $voucherA->code,
        'signal_type' => FraudSignalType::IpAddressAnomaly->value,
        'score' => 0.9,
        'risk_level' => FraudRiskLevel::High->value,
        'message' => 'Signal',
        'detector' => 'test',
        'was_blocked' => false,
        'reviewed' => false,
        'user_id' => 'user-x',
    ]);

    $campaign = Campaign::query()->create([
        'name' => 'AB Campaign',
        'slug' => 'ab-campaign',
        'status' => CampaignStatus::Active->value,
        'ab_testing_enabled' => true,
        'budget_cents' => 10000,
        'spent_cents' => 1000,
        'current_redemptions' => 10,
    ]);
    $campaign->assignOwner($ownerA)->save();

    $control = CampaignVariant::query()->create([
        'campaign_id' => $campaign->id,
        'name' => 'Control',
        'variant_code' => 'A',
        'traffic_percentage' => 50,
        'impressions' => 100,
        'applications' => 40,
        'conversions' => 10,
        'revenue_cents' => 10000,
        'discount_cents' => 500,
        'is_control' => true,
    ]);

    CampaignVariant::query()->create([
        'campaign_id' => $campaign->id,
        'name' => 'Treatment',
        'variant_code' => 'B',
        'traffic_percentage' => 50,
        'impressions' => 100,
        'applications' => 40,
        'conversions' => 15,
        'revenue_cents' => 15000,
        'discount_cents' => 700,
        'is_control' => false,
    ]);

    $giftCard = GiftCard::query()->create([
        'code' => 'GC-1',
        'initial_balance' => 1000,
        'current_balance' => 750,
        'currency' => 'USD',
        'status' => GiftCardStatus::Active->value,
    ]);
    $giftCard->assignOwner($ownerA)->save();

    GiftCardTransaction::query()->create([
        'gift_card_id' => $giftCard->id,
        'type' => GiftCardTransactionType::Redeem->value,
        'amount' => -250,
        'balance_before' => 1000,
        'balance_after' => 750,
        'description' => 'Redeemed',
    ]);

    // Stub GiftCardService for global widget stats
    app()->instance(GiftCardService::class, new class extends GiftCardService
    {
        public function getStatistics(?Model $owner = null): array
        {
            return [
                'total_cards' => 1,
                'active_cards' => 1,
                'total_issued_cents' => 1000,
                'total_outstanding_cents' => 750,
                'redemption_rate' => 25.0,
            ];
        }
    });

    // Widgets: invoke protected methods via reflection.
    foreach ([
        VoucherStatsWidget::class,
        CampaignStatsWidget::class,
        FraudStatsWidget::class,
        VoucherWalletStatsWidget::class,
    ] as $widgetClass) {
        $widget = app($widgetClass);

        $method = new ReflectionMethod($widgetClass, 'getStats');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBeArray();
    }

    $giftCardWidget = app(GiftCardStatsWidget::class);
    $method = new ReflectionMethod(GiftCardStatsWidget::class, 'getStats');
    $method->setAccessible(true);
    expect($method->invoke($giftCardWidget))->toBeArray();

    $giftCardWidget->record = $giftCard;
    expect($method->invoke($giftCardWidget))->toBeArray();

    $timeline = app(GiftCardTransactionTimelineWidget::class);
    $viewMethod = new ReflectionMethod(GiftCardTransactionTimelineWidget::class, 'getViewData');
    $viewMethod->setAccessible(true);
    expect($viewMethod->invoke($timeline)['transactions'])->toBeIterable();

    $timeline->record = $giftCard;
    expect($viewMethod->invoke($timeline)['transactions'])->not->toBeEmpty();

    $usageTimeline = app(VoucherUsageTimelineWidget::class);
    $usageTimeline->record = $voucherA;

    $events = $usageTimeline->getTimelineEvents();
    $summary = $usageTimeline->getSummaryStats();

    expect($events)->not->toBeEmpty();
    expect($summary['total_redemptions'])->toBe(1);

    // Cross-tenant read must return empty
    $usageTimeline->record = $voucherB;
    expect($usageTimeline->getTimelineEvents())->toBeEmpty();

    // Voucher cart stats should not crash without carts.
    $cartWidget = app(VoucherCartStatsWidget::class);
    $cartWidget->record = $voucherA;

    $cartStats = new ReflectionMethod(VoucherCartStatsWidget::class, 'getStats');
    $cartStats->setAccessible(true);

    expect($cartStats->invoke($cartWidget))->toBeArray();

    $chart = app(RedemptionTrendChart::class);

    $filters = new ReflectionMethod(RedemptionTrendChart::class, 'getFilters');
    $filters->setAccessible(true);
    expect($filters->invoke($chart))->toBeArray();

    $data = new ReflectionMethod(RedemptionTrendChart::class, 'getData');
    $data->setAccessible(true);
    expect($data->invoke($chart))->toHaveKeys(['datasets', 'labels']);

    // AB dashboard
    $dashboard = app(ABTestDashboard::class);
    $dashboard->mount();

    $dashboard->campaignId = $campaign->id;
    expect($dashboard->campaign)->not->toBeNull();
    expect($dashboard->variants)->not->toBeEmpty();

    $analysis = $dashboard->analysisData;

    expect($analysis)->toHaveKeys(['variants', 'suggestedWinner', 'hasEnoughData']);
    expect($analysis['variants'])->toHaveKey($control->variant_code);
});
