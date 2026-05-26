<?php

declare(strict_types=1);

use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use Mockery\Expectation;

use function Pest\Laravel\mock;

it('stores actual sequential discounts for stacked promotions', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-promotions-1';
    $session->subtotal = 10000;
    $session->grand_total = 10000;
    $session->currency = 'MYR';
    $session->billing_data = [];
    $session->cart_snapshot = ['items' => []];

    $launchPromotion = Promotion::query()->create([
        'name' => 'Launch 10',
        'code' => 'LAUNCH10',
        'type' => PromotionType::Percentage,
        'discount_value' => 10,
        'priority' => 20,
        'is_stackable' => true,
        'is_active' => true,
    ]);

    $vipPromotion = Promotion::query()->create([
        'name' => 'VIP 20',
        'code' => null,
        'type' => PromotionType::Percentage,
        'discount_value' => 20,
        'priority' => 10,
        'is_stackable' => true,
        'is_active' => true,
    ]);

    $service = mock(PromotionServiceInterface::class);
    /** @var Expectation $applicableExpectation */
    $applicableExpectation = $service->shouldReceive('getApplicablePromotions');
    $applicableExpectation->once()->andReturn(collect([
        $launchPromotion,
        $vipPromotion,
    ]));

    app()->instance(PromotionServiceInterface::class, $service);

    $result = (new PromotionsAdapter)->applyEligiblePromotions($session);

    expect($result['discount'])->toBe(2800)
        ->and($result['applied'])->toHaveCount(2)
        ->and($result['applied'][0])->toMatchArray([
            'promotion_id' => $launchPromotion->id,
            'name' => 'Launch 10',
            'discount' => 1000,
        ])
        ->and($result['applied'][1])->toMatchArray([
            'promotion_id' => $vipPromotion->id,
            'name' => 'VIP 20',
            'discount' => 1800,
        ]);
});

it('does not resolve a code promotion when the code belongs to a valid voucher', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-promotions-voucher-code';
    $session->subtotal = 10000;
    $session->grand_total = 10000;
    $session->currency = 'MYR';
    $session->billing_data = ['metadata' => ['promo_code' => 'SAVE10']];
    $session->cart_snapshot = ['items' => []];

    $automaticPromotion = Promotion::query()->create([
        'name' => 'Automatic Launch',
        'code' => null,
        'type' => PromotionType::Fixed,
        'discount_value' => 500,
        'priority' => 10,
        'is_stackable' => true,
        'is_active' => true,
    ]);

    $promotionService = mock(PromotionServiceInterface::class);
    $promotionService->shouldReceive('getApplicablePromotions')
        ->once()
        ->andReturn(collect([$automaticPromotion]));
    $promotionService->shouldNotReceive('findApplicableCodePromotion');

    app()->instance(PromotionServiceInterface::class, $promotionService);

    $voucherService = mock(VoucherServiceInterface::class);
    $voucherService->shouldReceive('validate')
        ->once()
        ->andReturn(VoucherValidationResult::valid());
    $voucherService->shouldReceive('find')
        ->once()
        ->andReturn(VoucherData::fromArray([
            'id' => 'voucher-save10',
            'code' => 'SAVE10',
            'name' => 'Save 10',
            'type' => VoucherType::Fixed->value,
            'value' => 1000,
            'currency' => 'MYR',
            'status' => Active::class,
        ]));

    app()->instance(VoucherServiceInterface::class, $voucherService);

    $result = (new PromotionsAdapter)->applyEligiblePromotions($session);

    expect($result['discount'])->toBe(500)
        ->and($result['applied'])->toHaveCount(1)
        ->and($result['applied'][0])->toMatchArray([
            'promotion_id' => $automaticPromotion->id,
            'name' => 'Automatic Launch',
            'discount' => 500,
        ]);
});

it('resolves applicable code promotions through the promotion service', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-promotions-code-promotion';
    $session->subtotal = 10000;
    $session->grand_total = 10000;
    $session->currency = 'MYR';
    $session->billing_data = ['metadata' => ['promo_code' => 'LAUNCH10']];
    $session->cart_snapshot = ['items' => []];

    $codePromotion = Promotion::query()->create([
        'name' => 'Launch 10',
        'code' => 'LAUNCH10',
        'type' => PromotionType::Percentage,
        'discount_value' => 10,
        'priority' => 50,
        'is_stackable' => true,
        'is_active' => true,
    ]);

    $promotionService = mock(PromotionServiceInterface::class);
    $promotionService->shouldReceive('getApplicablePromotions')
        ->once()
        ->andReturn(collect());
    $promotionService->shouldReceive('findApplicableCodePromotion')
        ->once()
        ->andReturn($codePromotion);

    app()->instance(PromotionServiceInterface::class, $promotionService);

    $voucherService = mock(VoucherServiceInterface::class);
    $voucherService->shouldReceive('validate')
        ->once()
        ->andReturn(VoucherValidationResult::invalid('Voucher not found.'));

    app()->instance(VoucherServiceInterface::class, $voucherService);

    $result = (new PromotionsAdapter)->applyEligiblePromotions($session);

    expect($result['discount'])->toBe(1000)
        ->and($result['applied'])->toHaveCount(1)
        ->and($result['applied'][0])->toMatchArray([
            'promotion_id' => $codePromotion->id,
            'name' => 'Launch 10',
            'code' => 'LAUNCH10',
            'discount' => 1000,
        ]);
});
