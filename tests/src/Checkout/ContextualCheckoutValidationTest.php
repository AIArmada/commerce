<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Contracts\ContextualBuyableValidatorInterface;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\ValidateCartStep;
use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\CommerceSupport\Support\PurchaseContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Str;
use Tests\Support\Cart\InMemoryStorage;

final class CheckoutContextValidator implements ContextualBuyableValidatorInterface
{
    public int $available = 2;

    public function validate(Buyable $buyable, int $quantity, PurchaseContext $context): CartValidationResult
    {
        return $quantity <= $this->available
            ? CartValidationResult::valid()
            : CartValidationResult::invalid('Selected resource is no longer available.');
    }
}

it('revalidates restored contextual cart selections before checkout', function (): void {
    $validator = new CheckoutContextValidator;
    app()->instance(ContextualBuyableValidatorInterface::class, $validator);

    $product = Product::query()->create([
        'name' => 'Checkout Context Product',
        'slug' => 'checkout-context-' . Str::random(8),
        'sku' => 'CTX-CHK-' . mb_strtoupper(Str::random(8)),
        'type' => ProductType::Service,
        'status' => ProductStatus::Active,
        'price' => 500,
        'currency' => 'USD',
    ]);

    $cart = new Cart(new InMemoryStorage, 'context-checkout-cart');
    $line = $cart->addBuyable($product, 1, purchaseContext: new PurchaseContext(['window' => 'morning']));

    $manager = Mockery::mock(CartManagerInterface::class);
    $manager->shouldReceive('getById')->with('context-checkout-cart')->andReturn($cart);
    $step = new ValidateCartStep($manager);

    $session = new CheckoutSession;
    $session->cart_id = 'context-checkout-cart';

    expect($step->validate($session))->toBe([]);

    $validator->available = 0;
    $errors = $step->validate($session);

    expect($errors)->toHaveKey('purchase_context.' . $line->id)
        ->and($errors['purchase_context.' . $line->id])->toContain('no longer available')
        ->and($step->handle($session)->status)->toBe(StepStatus::Failed);
});
