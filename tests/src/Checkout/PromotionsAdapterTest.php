<?php

declare(strict_types=1);

use AIArmada\Checkout\Integrations\PromotionsAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
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
