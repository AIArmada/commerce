<?php

declare(strict_types=1);

use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Runtime\PromotionServiceInterface;
use AIArmada\Promotions\Services\PromotionService;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Runtime\VoucherServiceInterface;
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
        ->andReturn(VoucherValidationResult::valid(['voucher' => VoucherData::fromArray([
            'id' => 'voucher-save10',
            'code' => 'SAVE10',
            'name' => 'Save 10',
            'type' => VoucherType::Fixed->value,
            'value' => 1000,
            'currency' => 'MYR',
            'status' => 'active',
        ])->toArray()]));
    $voucherService->shouldNotReceive('find');

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

it('evaluates minimum purchase and total unit quantity against the checkout snapshot', function (): void {
    app()->bind(PromotionServiceInterface::class, PromotionService::class);

    $session = new CheckoutSession;
    $session->id = 'session-promotions-targeting';
    $session->subtotal = 10000;
    $session->grand_total = 10000;
    $session->currency = 'MYR';
    $session->billing_data = [];
    $session->cart_snapshot = [
        'items' => [[
            'id' => 'line-1',
            'name' => 'Target Product',
            'quantity' => 3,
            'price' => 3000,
            'attributes' => ['sku' => 'TARGET-SKU', 'category' => 'target-category'],
        ]],
        'metadata' => ['channel' => 'web'],
    ];

    $eligible = Promotion::query()->create([
        'name' => 'Eligible',
        'type' => PromotionType::Fixed,
        'discount_value' => 1000,
        'min_purchase_amount' => 9000,
        'min_quantity' => 3,
        'is_active' => true,
    ]);

    Promotion::query()->create([
        'name' => 'Ineligible',
        'type' => PromotionType::Fixed,
        'discount_value' => 2000,
        'min_quantity' => 4,
        'is_active' => true,
    ]);

    $result = (new PromotionsAdapter)->applyEligiblePromotions($session);

    expect($result['discount'])->toBe(1000)
        ->and($result['applied'])->toHaveCount(1)
        ->and($result['applied'][0]['promotion_id'])->toBe($eligible->id);
});

it('exposes frozen snapshot product identifiers, quantities and categories to targeting', function (): void {
    $session = new CheckoutSession;
    $session->id = 'session-promotions-target-products';
    $session->subtotal = 10000;
    $session->grand_total = 10000;
    $session->currency = 'MYR';
    $session->billing_data = [];
    $session->cart_snapshot = ['items' => [[
        'id' => 'line-1',
        'name' => 'Target Product',
        'quantity' => 3,
        'price' => 3000,
        'attributes' => ['sku' => 'TARGET-SKU', 'category' => 'target-category'],
    ]]];

    $service = mock(PromotionServiceInterface::class);
    $service->shouldReceive('getApplicablePromotions')
        ->once()
        ->withArgs(function (TargetingContext $context): bool {
            expect($context->getCartValue())->toBe(10000)
                ->and($context->getCartQuantity())->toBe(3)
                ->and($context->getProductIdentifiers())->toBe(['TARGET-SKU'])
                ->and($context->getProductCategories())->toBe(['target-category'])
                ->and($context->getProductQuantity('TARGET-SKU'))->toBe(3);

            return true;
        })
        ->andReturn(collect());

    app()->instance(PromotionServiceInterface::class, $service);

    expect((new PromotionsAdapter)->applyEligiblePromotions($session)['discount'])->toBe(0);
});
