<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Facades\Chip;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('checkout uses pricing + tax + customers and remains owner-isolated', function (): void {
    config()->set('audit.enabled', true);
    config()->set('audit.console', true);
    config()->set('orders.audit.enabled', true);

    config()->set('chip.collect.api_key', 'test-api-key');
    config()->set('chip.collect.brand_id', 'test-brand-id');

    /** @var User $ownerA */
    $ownerA = User::factory()->create();

    /** @var User $ownerB */
    $ownerB = User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function (): Product {
        return Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $productB = OwnerContext::withOwner($ownerB, function (): Product {
        return Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-001',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $productA): void {
        PriceList::create([
            'name' => 'Retail',
            'slug' => 'retail',
            'currency' => 'MYR',
            'is_default' => true,
            'is_active' => true,
        ]);

        $priceList = PriceList::query()->firstOrFail();

        Price::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => $productA->getMorphClass(),
            'priceable_id' => $productA->getKey(),
            'amount' => 400_00,
            'currency' => 'MYR',
        ]);

        $zone = TaxZone::create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'priority' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'zone_id' => $zone->id,
            'tax_class' => 'standard',
            'name' => 'SST',
            'rate' => 600,
            'is_active' => true,
        ]);
    });

    $purchase = PurchaseData::from([
        'id' => (string) Str::uuid(),
        'checkout_url' => 'https://chip.test/checkout',
        'purchase' => [
            'total' => 0,
            'currency' => 'MYR',
            'products' => [],
        ],
        'client' => [],
    ]);

    Chip::shouldReceive('createPurchase')
        ->once()
        ->andReturn($purchase);

    /** @var TestCase $this */
    $this->actingAs($ownerA);

    $this->post(route('shop.cart.add'), [
        'product_id' => $productA->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('shop.cart.add'), [
        'product_id' => $productB->id,
        'quantity' => 1,
    ])->assertNotFound();

    $this->post(route('shop.checkout.process'), [
        'email' => 'buyer-a@example.com',
        'phone' => '+60123456789',
        'first_name' => 'A',
        'last_name' => 'Buyer',
        'line1' => '1 Jalan Demo',
        'line2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'WP Kuala Lumpur',
        'postcode' => '50000',
        'shipping_method' => 'free',
        'payment_method' => 'fpx',
    ])->assertRedirect('https://chip.test/checkout');

    $customerA = OwnerContext::withOwner($ownerA, fn () => Customer::query()->where('email', 'buyer-a@example.com')->first());
    expect($customerA)->not()->toBeNull();

    $checkoutSession = OwnerContext::withOwner($ownerA, fn () => CheckoutSession::query()->latest('created_at')->first());

    expect($checkoutSession)->not()->toBeNull()
        ->and($checkoutSession?->status instanceof AwaitingPayment)->toBeTrue()
        ->and($checkoutSession?->payment_redirect_url)->toBe('https://chip.test/checkout')
        ->and($checkoutSession?->subtotal)->toBe(800_00)
        ->and($checkoutSession?->tax_total)->toBe(48_00)
        ->and($checkoutSession?->grand_total)->toBe(848_00)
        ->and($checkoutSession?->customer_id)->toBe($customerA?->id)
        ->and($checkoutSession?->order_id)->toBeNull();

    $pendingOrder = OwnerContext::withOwner($ownerA, fn () => Order::query()->latest('created_at')->first());

    expect($pendingOrder)->toBeNull();

    OwnerContext::withOwner($ownerB, function () use ($checkoutSession): void {
        $shouldBeNull = CheckoutSession::query()->whereKey($checkoutSession?->id)->first();
        expect($shouldBeNull)->toBeNull();
    });
});

test('checkout builds CHIP purchase lines from exact order math without cart-level discount overrides', function (): void {
    config()->set('chip.collect.api_key', 'test-api-key');
    config()->set('chip.collect.brand_id', 'test-brand-id');

    /** @var User $owner */
    $owner = User::factory()->create();

    $product = OwnerContext::withOwner($owner, function (): Product {
        return Product::create([
            'name' => 'Discounted Widget',
            'sku' => 'DW-001',
            'price' => 10_000,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $zone = TaxZone::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'priority' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'zone_id' => $zone->id,
            'tax_class' => 'standard',
            'name' => 'SST',
            'rate' => 600,
            'is_active' => true,
        ]);
    });

    OwnerContext::withOwner($owner, function (): void {
        Voucher::create([
            'code' => 'FIXED30',
            'name' => 'RM 30 off',
            'description' => null,
            'type' => VoucherType::Fixed,
            'value' => 3_000,
            'currency' => 'MYR',
            'min_cart_value' => null,
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
        ]);
    });

    $capturedPurchasePayload = [];

    $purchase = PurchaseData::from([
        'id' => (string) Str::uuid(),
        'checkout_url' => 'https://chip.test/checkout',
        'purchase' => [
            'total' => 0,
            'currency' => 'MYR',
            'products' => [],
        ],
        'client' => [],
    ]);

    Chip::shouldReceive('createPurchase')
        ->once()
        ->andReturnUsing(function (array $data) use (&$capturedPurchasePayload, $purchase) {
            $capturedPurchasePayload = $data;

            return $purchase;
        });

    /** @var TestCase $this */
    $this->actingAs($owner);

    $this->post(route('shop.cart.add'), [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->post(route('shop.cart.voucher'), [
        'voucher_code' => 'FIXED30',
    ])->assertRedirect();

    $this->post(route('shop.checkout.process'), [
        'email' => 'discounted@example.com',
        'phone' => '+60123456789',
        'first_name' => 'Discounted',
        'last_name' => 'Buyer',
        'line1' => '1 Jalan Demo',
        'line2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'Selangor',
        'postcode' => '50000',
        'shipping_method' => 'free',
        'payment_method' => 'fpx',
    ])->assertRedirect('https://chip.test/checkout');

    $checkoutSession = OwnerContext::withOwner($owner, fn () => CheckoutSession::query()->latest('created_at')->first());

    expect($checkoutSession)->not()->toBeNull()
        ->and($checkoutSession?->status instanceof AwaitingPayment)->toBeTrue()
        ->and($checkoutSession?->payment_redirect_url)->toBe('https://chip.test/checkout')
        ->and($checkoutSession?->selected_payment_gateway)->toBe('chip')
        ->and($checkoutSession?->order_id)->toBeNull();

    expect(data_get($capturedPurchasePayload, 'purchase.total_discount_override'))->toBeNull();

    $products = collect(data_get($capturedPurchasePayload, 'purchase.products', []));
    $summaryProduct = $products->first();

    expect($products)->toHaveCount(1);
    expect(is_array($summaryProduct))->toBeTrue();

    if (! is_array($summaryProduct)) {
        return;
    }

    expect((string) ($summaryProduct['name'] ?? ''))->toContain('Order checkout - Session')
        ->and($summaryProduct['quantity'] ?? null)->toBe(1)
        ->and($summaryProduct['price'] ?? null)->toBe($checkoutSession?->grand_total);

    $payloadTotal = $products->sum(static fn (array $product): int => (((int) $product['price']) - ((int) ($product['discount'] ?? 0))) * (int) $product['quantity']);

    expect($payloadTotal)->toBe($checkoutSession?->grand_total);

    $pendingOrder = OwnerContext::withOwner($owner, fn () => Order::query()->latest('created_at')->first());

    expect($pendingOrder)->toBeNull();
});
