<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentVouchers\Actions\ApplyVoucherToCartAction;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
use AIArmada\FilamentVouchers\Pages\ABTestDashboard;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Exceptions\VoucherException;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Log;

uses(TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

it('covers AB dashboard header actions and cart voucher actions error paths', function (): void {
    Log::spy();

    $campaign = Campaign::query()->create([
        'name' => 'AB Campaign',
        'slug' => 'ab-campaign-2',
        'status' => CampaignStatus::Active->value,
        'ab_testing_enabled' => true,
    ]);

    CampaignVariant::query()->create([
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

    $dashboard = app(ABTestDashboard::class);
    $dashboard->campaignId = $campaign->id;

    $method = new ReflectionMethod(ABTestDashboard::class, 'getHeaderActions');
    $method->setAccessible(true);

    /** @var array<int, Action> $actions */
    $actions = $method->invoke($dashboard);

    expect($actions)->toHaveCount(2);

    $selectCampaign = $actions[0];
    $selectFn = $selectCampaign->getActionFunction();
    $selectFn?->__invoke(['campaign_id' => $campaign->id]);
    expect($dashboard->campaignId)->toBe($campaign->id);

    $declareWinner = $actions[1];
    $winnerFn = $declareWinner->getActionFunction();
    $winnerFn?->__invoke(['winner_variant' => 'B']);

    // Cart actions: force error paths.
    $cart = Cart::query()->create([
        'instance' => 'default',
        'identifier' => 'cart-actions',
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            return new class
            {
                public function applyVoucher(string $code): void
                {
                    throw new VoucherException('Cannot apply');
                }

                public function removeVoucher(string $code): void
                {
                    throw new RuntimeException('Cannot remove');
                }

                public function getAppliedVouchers(): array
                {
                    return [];
                }
            };
        }
    });

    $apply = CartVoucherActions::applyVoucher();
    $applyFn = $apply->getActionFunction();

    // Empty code branch
    $applyFn?->__invoke(['voucher_code' => ''], $cart);

    // VoucherException branch
    $applyFn?->__invoke(['voucher_code' => 'FAIL'], $cart);

    $remove = CartVoucherActions::removeVoucher('FAIL');
    $removeFn = $remove->getActionFunction();
    $removeFn?->__invoke($cart);

    // Action used on Cart pages.
    $cartApplyAction = ApplyVoucherToCartAction::make();
    $cartApplyFn = $cartApplyAction->getActionFunction();

    $cartApplyFn?->__invoke(['voucher_code' => 'FAIL'], $cart);

    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            return new class
            {
                public function applyVoucher(string $code): void
                {
                    throw new Exception('Boom');
                }
            };
        }
    });

    $cartApplyFn?->__invoke(['voucher_code' => 'ERR'], $cart);
});
