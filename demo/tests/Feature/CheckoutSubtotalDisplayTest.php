<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('checkout displays pre-discount subtotal when a voucher is applied', function (): void {
    $owner = \App\Models\User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::setForRequest($owner);

    $product = Product::create([
        'name' => 'AirPods Pro',
        'sku' => 'APP-2-001',
        'price' => 109_900,
        'currency' => 'MYR',
        'status' => ProductStatus::Active,
    ]);

    $product->assignOwner($owner)->save();

    Voucher::create([
        'code' => 'LOYAL100-TEST',
        'name' => 'RM 100 off',
        'description' => null,
        'type' => VoucherType::Fixed,
        'value' => 10_000,
        'currency' => 'MYR',
        'min_cart_value' => 20_000,
        'max_discount' => null,
        'usage_limit' => null,
        'usage_limit_per_user' => null,
        'applied_count' => 0,
        'allows_manual_redemption' => true,
        'starts_at' => null,
        'expires_at' => null,
        'status' => Active::class,
        'target_definition' => null,
        'metadata' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('shop.cart.voucher'), [
        'voucher_code' => 'LOYAL100-TEST',
    ])->assertRedirect();

    $response = $this->get(route('shop.checkout'));

    $response->assertOk();

    $html = (string) $response->getContent();

    expect($html)->toMatch('/<span>\s*Subtotal\s*<\/span>\s*<span>\s*RM\s*2,198\.00\s*<\/span>/');
    expect($html)->toMatch('/<span>\s*Voucher Discount\s*<\/span>\s*<span>\s*-RM\s*100\.00\s*<\/span>/');
    expect($html)->not()->toContain('voucher_LOYAL100-TEST');
});

test('cart displays voucher discount and discounted cart total when a voucher is applied', function (): void {
    $owner = \App\Models\User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::setForRequest($owner);

    $product = Product::create([
        'name' => 'AirPods Pro',
        'sku' => 'APP-2-001',
        'price' => 109_900,
        'currency' => 'MYR',
        'status' => ProductStatus::Active,
    ]);

    $product->assignOwner($owner)->save();

    Voucher::create([
        'code' => 'LOYAL100-TEST',
        'name' => 'RM 100 off',
        'description' => null,
        'type' => VoucherType::Fixed,
        'value' => 10_000,
        'currency' => 'MYR',
        'min_cart_value' => 20_000,
        'max_discount' => null,
        'usage_limit' => null,
        'usage_limit_per_user' => null,
        'applied_count' => 0,
        'allows_manual_redemption' => true,
        'starts_at' => null,
        'expires_at' => null,
        'status' => Active::class,
        'target_definition' => null,
        'metadata' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('shop.cart.voucher'), [
        'voucher_code' => 'LOYAL100-TEST',
    ])->assertRedirect();

    $response = $this->get(route('shop.cart'));

    $response->assertOk();

    $html = (string) $response->getContent();

    expect($html)->toMatch('/<span>\s*Subtotal \(2 items\)\s*<\/span>\s*<span>\s*RM\s*2,198\.00\s*<\/span>/');
    expect($html)->toMatch('/<span>\s*Voucher Discount\s*<\/span>\s*<span>\s*-RM\s*100\.00\s*<\/span>/');
    expect($html)->toMatch('/<span>\s*Cart Total\s*<\/span>\s*<span>\s*RM\s*2,098\.00\s*<\/span>/');
    expect($html)->toContain('Calculated at checkout');
    expect($html)->not()->toContain('voucher_LOYAL100-TEST');
});

test('cart displays percentage voucher discount and discounted cart total after reload', function (): void {
    $owner = \App\Models\User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::setForRequest($owner);

    $product = Product::create([
        'name' => 'AirPods Pro',
        'sku' => 'APP-2-001',
        'price' => 109_900,
        'currency' => 'MYR',
        'status' => ProductStatus::Active,
    ]);

    $product->assignOwner($owner)->save();

    Voucher::create([
        'code' => 'FLASH50-TEST',
        'name' => '50% off',
        'description' => null,
        'type' => VoucherType::Percentage,
        'value' => 5_000,
        'currency' => 'MYR',
        'min_cart_value' => 10_000,
        'max_discount' => 50_000,
        'usage_limit' => null,
        'usage_limit_per_user' => null,
        'applied_count' => 0,
        'allows_manual_redemption' => true,
        'starts_at' => null,
        'expires_at' => null,
        'status' => Active::class,
        'target_definition' => null,
        'metadata' => null,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('shop.cart.voucher'), [
        'voucher_code' => 'FLASH50-TEST',
    ])->assertRedirect();

    $response = $this->get(route('shop.cart'));

    $response->assertOk();

    $html = (string) $response->getContent();

    expect($html)->toMatch('/<span>\s*Subtotal \(2 items\)\s*<\/span>\s*<span>\s*RM\s*2,198\.00\s*<\/span>/');
    expect($html)->toMatch('/<span>\s*Voucher Discount\s*<\/span>\s*<span>\s*-RM\s*1,099\.00\s*<\/span>/');
    expect($html)->toMatch('/<span>\s*Cart Total\s*<\/span>\s*<span>\s*RM\s*1,099\.00\s*<\/span>/');
    expect($html)->not()->toContain('voucher_FLASH50-TEST');
});

test('free shipping is rejected on the backend when the minimum subtotal is not met', function (): void {
    $owner = \App\Models\User::factory()->create([
        'email' => 'admin@commerce.demo',
    ]);

    OwnerContext::setForRequest($owner);

    $product = Product::create([
        'name' => 'Small Accessory',
        'sku' => 'ACC-001',
        'price' => 5_000,
        'currency' => 'MYR',
        'status' => ProductStatus::Active,
    ]);

    $product->assignOwner($owner)->save();

    /** @var \Tests\TestCase $this */
    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect();

    $this->post(route('shop.checkout.process'), [
        'email' => 'buyer@example.com',
        'phone' => '+60123456789',
        'first_name' => 'Demo',
        'last_name' => 'Buyer',
        'line1' => '1 Jalan Demo',
        'line2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'Selangor',
        'postcode' => '50000',
        'shipping_method' => 'free',
        'payment_method' => 'fpx',
    ])->assertSessionHasErrors('shipping_method');

    OwnerContext::withOwner($owner, function (): void {
        expect(Order::query()->count())->toBe(0);
    });
});
