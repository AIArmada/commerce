<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\CalculatePricingStep;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Products\Models\Product;

use function Pest\Laravel\mock;

it('rejects cross-tenant associated models when resolving checkout pricing', function (): void {
    config()->set('checkout.owner.enabled', true);
    config()->set('checkout.owner.include_global', false);
    config()->set('checkout.owner.auto_assign_on_create', true);

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
    config()->set('products.features.owner.auto_assign_on_create', true);

    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $productOwnedByB = OwnerContext::withOwner($ownerB, static function (): Product {
        return Product::query()->create([
            'name' => 'Owner B Product',
            'price' => 2500,
            'currency' => 'MYR',
        ]);
    });

    $calculator = mock(PriceCalculatorInterface::class);
    $calculator->shouldNotReceive('calculate');
    app()->instance(PriceCalculatorInterface::class, $calculator);

    $session = OwnerContext::withOwner($ownerA, static function () use ($productOwnedByB): CheckoutSession {
        return CheckoutSession::query()->create([
            'cart_id' => 'cross-tenant-pricing-cart',
            'currency' => 'MYR',
            'cart_snapshot' => [
                'items' => [
                    [
                        'id' => 'line-1',
                        'product_id' => $productOwnedByB->id,
                        'price' => 2500,
                        'quantity' => 1,
                        'associated_model' => [
                            'class' => Product::class,
                            'id' => $productOwnedByB->id,
                        ],
                    ],
                ],
            ],
        ]);
    });

    $result = app(CalculatePricingStep::class)->handle($session);

    $session->refresh();

    expect($result->isSuccessful())->toBeTrue()
        ->and($session->subtotal)->toBe(2500)
        ->and($session->pricing_data['items'][0]['unit_price'] ?? null)->toBe(2500)
        ->and($session->pricing_data['items'][0]['original_unit_price'] ?? null)->toBeNull();
});
