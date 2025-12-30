<?php

declare(strict_types=1);

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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(\Tests\TestCase::class, RefreshDatabase::class);

test('checkout uses pricing + tax + customers and remains owner-isolated', function (): void {
    /** @var \App\Models\User $ownerA */
    $ownerA = \App\Models\User::factory()->create();

    /** @var \App\Models\User $ownerB */
    $ownerB = \App\Models\User::factory()->create();

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

    OwnerContext::withOwner($ownerA, function () use ($productA): void {
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
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'priority' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'tax_class' => 'standard',
            'name' => 'SST',
            'rate' => 600,
            'is_active' => true,
        ]);
    });

    Chip::shouldReceive('purchase')->andReturn(new class
    {
        public function currency(string $currency): static
        {
            return $this;
        }

        public function reference(string $reference): static
        {
            return $this;
        }

        public function customer(string $email, string $fullName, string $phone, string $country): static
        {
            return $this;
        }

        public function billingAddress(string $streetAddress, string $city, string $zipCode, string $state, string $country): static
        {
            return $this;
        }

        public function addProduct(string $name, int $price, int $quantity): static
        {
            return $this;
        }

        public function discount(int $amount): static
        {
            return $this;
        }

        public function redirects(string $successUrl, string $failureUrl, string $cancelUrl): static
        {
            return $this;
        }

        public function webhook(string $url): static
        {
            return $this;
        }

        /** @param array<string, mixed> $metadata */
        public function metadata(array $metadata): static
        {
            return $this;
        }

        public function create(): PurchaseData
        {
            return PurchaseData::from([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'checkout_url' => 'https://chip.test/checkout',
                'purchase' => [
                    'total' => 0,
                    'currency' => 'MYR',
                    'products' => [],
                ],
                'client' => [],
            ]);
        }
    });

    /** @var \Tests\TestCase $this */
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
        'address_line_1' => '1 Jalan Demo',
        'address_line_2' => null,
        'city' => 'Kuala Lumpur',
        'state' => 'WP Kuala Lumpur',
        'postcode' => '50000',
        'shipping_method' => 'free',
        'payment_method' => 'fpx',
    ])->assertRedirect('https://chip.test/checkout');

    $customerA = OwnerContext::withOwner($ownerA, fn () => Customer::query()->where('email', 'buyer-a@example.com')->first());
    expect($customerA)->not()->toBeNull();

    $orderA = OwnerContext::withOwner($ownerA, fn () => Order::query()->latest('created_at')->first());
    expect($orderA)->not()->toBeNull();

    expect($orderA->subtotal)->toBe(800_00);
    expect($orderA->tax_total)->toBe(48_00);
    expect($orderA->grand_total)->toBe(848_00);
    expect($orderA->customer_type)->toBe($customerA->getMorphClass());
    expect($orderA->customer_id)->toBe($customerA->id);

    OwnerContext::withOwner($ownerB, function () use ($orderA): void {
        $shouldBeNull = Order::query()->whereKey($orderA->id)->first();
        expect($shouldBeNull)->toBeNull();
    });
});
