<?php

declare(strict_types=1);

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Services\ChipCollectService;

describe('PurchaseBuilder', function (): void {
    beforeEach(function (): void {
        config([
            'chip.collect.brand_id' => 'test-brand-id',
        ]);

        $this->service = Mockery::mock(ChipCollectService::class);
        $this->builder = new PurchaseBuilder($this->service);
    });

    it('can build a basic purchase with required fields', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test Product', 5000)
            ->email('test@example.com')
            ->toArray();

        expect($data)->toHaveKey('purchase')
            ->and($data['purchase'])->toHaveKey('currency', 'MYR')
            ->and($data['purchase']['products'])->toHaveCount(1)
            ->and($data['purchase']['products'][0])->toBe([
                'name' => 'Test Product',
                'price' => 5000,
                'quantity' => '1',
            ])
            ->and($data)->toHaveKey('client')
            ->and($data['client'])->toHaveKey('email', 'test@example.com');
    });

    it('can add multiple products', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Product 1', 1000, 2)
            ->addProduct('Product 2', 2000, 1, 100, 6.0)
            ->email('test@example.com')
            ->toArray();

        expect($data['purchase']['products'])->toHaveCount(2)
            ->and($data['purchase']['products'][0])->toBe([
                'name' => 'Product 1',
                'price' => 1000,
                'quantity' => '2',
            ])
            ->and($data['purchase']['products'][1])->toBe([
                'name' => 'Product 2',
                'price' => 2000,
                'quantity' => '1',
                'discount' => 100,
                'tax_percent' => 6.0,
            ]);
    });

    it('can set customer details using customer method', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->customer('john@example.com', 'John Doe', '+60123456789', 'MY')
            ->toArray();

        expect($data['client'])->toBe([
            'email' => 'john@example.com',
            'full_name' => 'John Doe',
            'phone' => '+60123456789',
            'country' => 'MY',
        ]);
    });

    it('can set billing address', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->billingAddress('123 Main St', 'Kuala Lumpur', '50000', 'Selangor', 'MY')
            ->toArray();

        expect($data['client'])->toHaveKey('street_address', '123 Main St')
            ->and($data['client'])->toHaveKey('city', 'Kuala Lumpur')
            ->and($data['client'])->toHaveKey('zip_code', '50000')
            ->and($data['client'])->toHaveKey('state', 'Selangor')
            ->and($data['client'])->toHaveKey('country', 'MY');
    });

    it('can set shipping address', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->shippingAddress('456 Oak Ave', 'Penang', '10000', 'Penang', 'MY')
            ->toArray();

        expect($data['client'])->toHaveKey('shipping_street_address', '456 Oak Ave')
            ->and($data['client'])->toHaveKey('shipping_city', 'Penang')
            ->and($data['client'])->toHaveKey('shipping_zip_code', '10000')
            ->and($data['client'])->toHaveKey('shipping_state', 'Penang')
            ->and($data['client'])->toHaveKey('shipping_country', 'MY');
    });

    it('can set all redirect URLs at once', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->redirects(
                'https://example.com/success',
                'https://example.com/failure',
                'https://example.com/cancel'
            )
            ->toArray();

        expect($data)->toHaveKey('success_redirect', 'https://example.com/success')
            ->and($data)->toHaveKey('failure_redirect', 'https://example.com/failure')
            ->and($data)->toHaveKey('cancel_redirect', 'https://example.com/cancel');
    });

    it('can set individual redirect URLs', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->successUrl('https://example.com/success')
            ->failureUrl('https://example.com/failure')
            ->cancelUrl('https://example.com/cancel')
            ->toArray();

        expect($data)->toHaveKey('success_redirect', 'https://example.com/success')
            ->and($data)->toHaveKey('failure_redirect', 'https://example.com/failure')
            ->and($data)->toHaveKey('cancel_redirect', 'https://example.com/cancel');
    });

    it('can set webhook callback URL', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->webhook('https://example.com/webhooks/chip')
            ->toArray();

        expect($data)->toHaveKey('success_callback', 'https://example.com/webhooks/chip');
    });

    it('can set reference', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->reference('ORDER-2025-001')
            ->toArray();

        expect($data)->toHaveKey('reference', 'ORDER-2025-001');
    });

    it('can enable send receipt', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->sendReceipt(true)
            ->toArray();

        expect($data)->toHaveKey('send_receipt', true);
    });

    it('can enable pre-authorization', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->preAuthorize(true)
            ->toArray();

        expect($data)->toHaveKey('skip_capture', true);
    });

    it('can force recurring', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->forceRecurring(true)
            ->toArray();

        expect($data)->toHaveKey('force_recurring', true);
    });

    it('can set due date', function (): void {
        $dueDate = now()->addDays(7)->timestamp;

        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->due($dueDate)
            ->toArray();

        expect($data)->toHaveKey('due', $dueDate);
    });

    it('can set notes', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->notes('This is a test purchase')
            ->toArray();

        expect($data['purchase'])->toHaveKey('notes', 'This is a test purchase');
    });

    it('can override brand ID', function (): void {
        $data = $this->builder
            ->brand('custom-brand-id')
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->toArray();

        expect($data)->toHaveKey('brand_id', 'custom-brand-id');
    });

    it('can set client ID', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->clientId('existing-client-id')
            ->toArray();

        expect($data)->toHaveKey('client_id', 'existing-client-id');
    });

    it('supports method chaining for fluent API', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProduct('Premium Plan', 9900, 1, 0, 6.0, 'subscription')
            ->customer('customer@example.com', 'John Doe', '+60123456789', 'MY')
            ->billingAddress('123 Main St', 'KL', '50000', 'Selangor', 'MY')
            ->reference('ORDER-2025-001')
            ->successUrl('https://example.com/success')
            ->webhook('https://example.com/webhooks/chip')
            ->sendReceipt(true)
            ->toArray();

        expect($data)->toBeArray()
            ->and($data['purchase']['products'])->toHaveCount(1)
            ->and($data['client']['email'])->toBe('customer@example.com')
            ->and($data['reference'])->toBe('ORDER-2025-001');
    });

    it('can create purchase using create method', function (): void {
        $mockPurchase = Mockery::mock(AIArmada\Chip\DataObjects\Purchase::class);

        $this->service->shouldReceive('createPurchase')
            ->once()
            ->andReturn($mockPurchase);

        $result = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->create();

        expect($result)->toBeInstanceOf(AIArmada\Chip\DataObjects\Purchase::class);
    });

    it('can create purchase using save method alias', function (): void {
        $mockPurchase = Mockery::mock(AIArmada\Chip\DataObjects\Purchase::class);

        $this->service->shouldReceive('createPurchase')
            ->once()
            ->andReturn($mockPurchase);

        $result = $this->builder
            ->currency('MYR')
            ->addProduct('Test', 1000)
            ->email('test@example.com')
            ->save();

        expect($result)->toBeInstanceOf(AIArmada\Chip\DataObjects\Purchase::class);
    });
});
