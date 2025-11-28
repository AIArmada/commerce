<?php

declare(strict_types=1);

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Akaunting\Money\Money;

describe('PurchaseBuilder Interface Integration', function (): void {
    beforeEach(function (): void {
        config([
            'chip.collect.brand_id' => 'test-brand-id',
        ]);

        $this->service = Mockery::mock(ChipCollectService::class);
        $this->builder = new PurchaseBuilder($this->service);
    });

    describe('addProductMoney', function (): void {
        it('adds product with Money price', function (): void {
            $data = $this->builder
                ->currency('MYR')
                ->addProductMoney('Premium Product', Money::MYR(9900), 2)
                ->email('test@example.com')
                ->toArray();

            expect($data['purchase']['products'])->toHaveCount(1)
                ->and($data['purchase']['products'][0]['name'])->toBe('Premium Product')
                ->and($data['purchase']['products'][0]['price'])->toBe(9900)
                ->and($data['purchase']['products'][0]['quantity'])->toBe('2');
        });

        it('includes discount when provided with Money', function (): void {
            $data = $this->builder
                ->currency('MYR')
                ->addProductMoney('Discounted Product', Money::MYR(10000), 1, Money::MYR(1000), 6.0)
                ->email('test@example.com')
                ->toArray();

            expect($data['purchase']['products'][0]['discount'])->toBe(1000)
                ->and($data['purchase']['products'][0]['tax_percent'])->toBe(6.0);
        });
    });

    describe('addLineItem', function (): void {
        it('adds line item from LineItemInterface', function (): void {
            $lineItem = Mockery::mock(LineItemInterface::class);
            $lineItem->shouldReceive('getLineItemName')->andReturn('Widget');
            $lineItem->shouldReceive('getLineItemPrice')->andReturn(Money::MYR(5000));
            $lineItem->shouldReceive('getLineItemQuantity')->andReturn(3);
            $lineItem->shouldReceive('getLineItemDiscount')->andReturn(Money::MYR(500));
            $lineItem->shouldReceive('getLineItemTaxPercent')->andReturn(6.0);
            $lineItem->shouldReceive('getLineItemCategory')->andReturn('electronics');

            $data = $this->builder
                ->currency('MYR')
                ->addLineItem($lineItem)
                ->email('test@example.com')
                ->toArray();

            expect($data['purchase']['products'])->toHaveCount(1)
                ->and($data['purchase']['products'][0]['name'])->toBe('Widget')
                ->and($data['purchase']['products'][0]['price'])->toBe(5000)
                ->and($data['purchase']['products'][0]['quantity'])->toBe('3')
                ->and($data['purchase']['products'][0]['discount'])->toBe(500)
                ->and($data['purchase']['products'][0]['tax_percent'])->toBe(6.0);
        });

        it('handles line item without discount', function (): void {
            $lineItem = Mockery::mock(LineItemInterface::class);
            $lineItem->shouldReceive('getLineItemName')->andReturn('Simple Product');
            $lineItem->shouldReceive('getLineItemPrice')->andReturn(Money::MYR(2500));
            $lineItem->shouldReceive('getLineItemQuantity')->andReturn(1);
            $lineItem->shouldReceive('getLineItemDiscount')->andReturn(Money::MYR(0));
            $lineItem->shouldReceive('getLineItemTaxPercent')->andReturn(0.0);
            $lineItem->shouldReceive('getLineItemCategory')->andReturn(null);

            $data = $this->builder
                ->currency('MYR')
                ->addLineItem($lineItem)
                ->email('test@example.com')
                ->toArray();

            expect($data['purchase']['products'][0]['name'])->toBe('Simple Product')
                ->and($data['purchase']['products'][0]['price'])->toBe(2500);
        });
    });

    describe('fromCheckoutable', function (): void {
        it('builds purchase from CheckoutableInterface', function (): void {
            $lineItem1 = Mockery::mock(LineItemInterface::class);
            $lineItem1->shouldReceive('getLineItemName')->andReturn('Product A');
            $lineItem1->shouldReceive('getLineItemPrice')->andReturn(Money::MYR(5000));
            $lineItem1->shouldReceive('getLineItemQuantity')->andReturn(2);
            $lineItem1->shouldReceive('getLineItemDiscount')->andReturn(Money::MYR(0));
            $lineItem1->shouldReceive('getLineItemTaxPercent')->andReturn(0.0);
            $lineItem1->shouldReceive('getLineItemCategory')->andReturn(null);

            $lineItem2 = Mockery::mock(LineItemInterface::class);
            $lineItem2->shouldReceive('getLineItemName')->andReturn('Product B');
            $lineItem2->shouldReceive('getLineItemPrice')->andReturn(Money::MYR(3000));
            $lineItem2->shouldReceive('getLineItemQuantity')->andReturn(1);
            $lineItem2->shouldReceive('getLineItemDiscount')->andReturn(Money::MYR(300));
            $lineItem2->shouldReceive('getLineItemTaxPercent')->andReturn(6.0);
            $lineItem2->shouldReceive('getLineItemCategory')->andReturn('electronics');

            $checkoutable = Mockery::mock(CheckoutableInterface::class);
            $checkoutable->shouldReceive('getCheckoutCurrency')->andReturn('MYR');
            $checkoutable->shouldReceive('getCheckoutLineItems')->andReturn([$lineItem1, $lineItem2]);
            $checkoutable->shouldReceive('getCheckoutReference')->andReturn('CART-001');
            $checkoutable->shouldReceive('getCheckoutNotes')->andReturn('Please deliver quickly');
            $checkoutable->shouldReceive('getCheckoutMetadata')->andReturn(['order_id' => 'ORDER-123']);

            $data = $this->builder
                ->fromCheckoutable($checkoutable)
                ->email('customer@example.com')
                ->toArray();

            expect($data['purchase']['currency'])->toBe('MYR')
                ->and($data['purchase']['products'])->toHaveCount(2)
                ->and($data['reference'])->toBe('CART-001')
                ->and($data['purchase']['notes'])->toBe('Please deliver quickly');
        });

        it('handles checkoutable without notes', function (): void {
            $lineItem = Mockery::mock(LineItemInterface::class);
            $lineItem->shouldReceive('getLineItemName')->andReturn('Product');
            $lineItem->shouldReceive('getLineItemPrice')->andReturn(Money::MYR(1000));
            $lineItem->shouldReceive('getLineItemQuantity')->andReturn(1);
            $lineItem->shouldReceive('getLineItemDiscount')->andReturn(Money::MYR(0));
            $lineItem->shouldReceive('getLineItemTaxPercent')->andReturn(0.0);
            $lineItem->shouldReceive('getLineItemCategory')->andReturn(null);

            $checkoutable = Mockery::mock(CheckoutableInterface::class);
            $checkoutable->shouldReceive('getCheckoutCurrency')->andReturn('MYR');
            $checkoutable->shouldReceive('getCheckoutLineItems')->andReturn([$lineItem]);
            $checkoutable->shouldReceive('getCheckoutReference')->andReturn('REF-001');
            $checkoutable->shouldReceive('getCheckoutNotes')->andReturn(null);
            $checkoutable->shouldReceive('getCheckoutMetadata')->andReturn([]);

            $data = $this->builder
                ->fromCheckoutable($checkoutable)
                ->email('test@example.com')
                ->toArray();

            expect($data['purchase'])->not->toHaveKey('notes');
        });
    });

    describe('fromCustomer', function (): void {
        it('builds customer from CustomerInterface', function (): void {
            $customer = Mockery::mock(CustomerInterface::class);
            $customer->shouldReceive('getCustomerEmail')->andReturn('john@example.com');
            $customer->shouldReceive('getCustomerName')->andReturn('John Doe');
            $customer->shouldReceive('getCustomerPhone')->andReturn('+60123456789');
            $customer->shouldReceive('getCustomerCountry')->andReturn('MY');
            $customer->shouldReceive('getBillingStreetAddress')->andReturn('123 Main St');
            $customer->shouldReceive('getBillingCity')->andReturn('Kuala Lumpur');
            $customer->shouldReceive('getBillingPostalCode')->andReturn('50000');
            $customer->shouldReceive('getBillingState')->andReturn('Selangor');
            $customer->shouldReceive('getBillingCountry')->andReturn('MY');
            $customer->shouldReceive('hasShippingAddress')->andReturn(false);
            $customer->shouldReceive('getGatewayCustomerId')->andReturn(null);

            $data = $this->builder
                ->currency('MYR')
                ->addProduct('Test Product', 1000)
                ->fromCustomer($customer)
                ->toArray();

            expect($data['client']['email'])->toBe('john@example.com')
                ->and($data['client']['full_name'])->toBe('John Doe')
                ->and($data['client']['phone'])->toBe('+60123456789')
                ->and($data['client']['country'])->toBe('MY')
                ->and($data['client']['street_address'])->toBe('123 Main St')
                ->and($data['client']['city'])->toBe('Kuala Lumpur')
                ->and($data['client']['zip_code'])->toBe('50000')
                ->and($data['client']['state'])->toBe('Selangor');
        });

        it('handles customer with shipping address', function (): void {
            $customer = Mockery::mock(CustomerInterface::class);
            $customer->shouldReceive('getCustomerEmail')->andReturn('jane@example.com');
            $customer->shouldReceive('getCustomerName')->andReturn('Jane Doe');
            $customer->shouldReceive('getCustomerPhone')->andReturn(null);
            $customer->shouldReceive('getCustomerCountry')->andReturn('MY');
            $customer->shouldReceive('getBillingStreetAddress')->andReturn(null);
            $customer->shouldReceive('hasShippingAddress')->andReturn(true);
            $customer->shouldReceive('getShippingStreetAddress')->andReturn('456 Oak Ave');
            $customer->shouldReceive('getShippingCity')->andReturn('Penang');
            $customer->shouldReceive('getShippingPostalCode')->andReturn('10000');
            $customer->shouldReceive('getShippingState')->andReturn('Penang');
            $customer->shouldReceive('getShippingCountry')->andReturn('MY');
            $customer->shouldReceive('getGatewayCustomerId')->andReturn(null);

            $data = $this->builder
                ->currency('MYR')
                ->addProduct('Test Product', 1000)
                ->fromCustomer($customer)
                ->toArray();

            expect($data['client']['shipping_street_address'])->toBe('456 Oak Ave')
                ->and($data['client']['shipping_city'])->toBe('Penang')
                ->and($data['client']['shipping_zip_code'])->toBe('10000');
        });

        it('handles customer with minimal data', function (): void {
            $customer = Mockery::mock(CustomerInterface::class);
            $customer->shouldReceive('getCustomerEmail')->andReturn('minimal@example.com');
            $customer->shouldReceive('getCustomerName')->andReturn(null);
            $customer->shouldReceive('getCustomerPhone')->andReturn(null);
            $customer->shouldReceive('getCustomerCountry')->andReturn(null);
            $customer->shouldReceive('getBillingStreetAddress')->andReturn(null);
            $customer->shouldReceive('hasShippingAddress')->andReturn(false);
            $customer->shouldReceive('getGatewayCustomerId')->andReturn(null);

            $data = $this->builder
                ->currency('MYR')
                ->addProduct('Test Product', 1000)
                ->fromCustomer($customer)
                ->toArray();

            expect($data['client']['email'])->toBe('minimal@example.com')
                ->and($data['client'])->not->toHaveKey('full_name')
                ->and($data['client'])->not->toHaveKey('phone');
        });
    });

    describe('combined usage', function (): void {
        it('builds complete purchase from checkoutable and customer', function (): void {
            // Mock line items
            $lineItem = Mockery::mock(LineItemInterface::class);
            $lineItem->shouldReceive('getLineItemName')->andReturn('Premium Widget');
            $lineItem->shouldReceive('getLineItemPrice')->andReturn(Money::MYR(9900));
            $lineItem->shouldReceive('getLineItemQuantity')->andReturn(1);
            $lineItem->shouldReceive('getLineItemDiscount')->andReturn(Money::MYR(990));
            $lineItem->shouldReceive('getLineItemTaxPercent')->andReturn(6.0);
            $lineItem->shouldReceive('getLineItemCategory')->andReturn('electronics');

            // Mock checkoutable
            $checkoutable = Mockery::mock(CheckoutableInterface::class);
            $checkoutable->shouldReceive('getCheckoutCurrency')->andReturn('MYR');
            $checkoutable->shouldReceive('getCheckoutLineItems')->andReturn([$lineItem]);
            $checkoutable->shouldReceive('getCheckoutReference')->andReturn('ORDER-2025-001');
            $checkoutable->shouldReceive('getCheckoutNotes')->andReturn(null);
            $checkoutable->shouldReceive('getCheckoutMetadata')->andReturn([]);

            // Mock customer
            $customer = Mockery::mock(CustomerInterface::class);
            $customer->shouldReceive('getCustomerEmail')->andReturn('customer@example.com');
            $customer->shouldReceive('getCustomerName')->andReturn('John Customer');
            $customer->shouldReceive('getCustomerPhone')->andReturn('+60123456789');
            $customer->shouldReceive('getCustomerCountry')->andReturn('MY');
            $customer->shouldReceive('getBillingStreetAddress')->andReturn('123 Main St');
            $customer->shouldReceive('getBillingCity')->andReturn('KL');
            $customer->shouldReceive('getBillingPostalCode')->andReturn('50000');
            $customer->shouldReceive('getBillingState')->andReturn('Selangor');
            $customer->shouldReceive('getBillingCountry')->andReturn('MY');
            $customer->shouldReceive('hasShippingAddress')->andReturn(false);
            $customer->shouldReceive('getGatewayCustomerId')->andReturn(null);

            $data = $this->builder
                ->fromCheckoutable($checkoutable)
                ->fromCustomer($customer)
                ->successUrl('https://example.com/success')
                ->failureUrl('https://example.com/failed')
                ->webhook('https://example.com/webhooks/chip')
                ->sendReceipt(true)
                ->toArray();

            // Verify purchase data
            expect($data['purchase']['currency'])->toBe('MYR')
                ->and($data['purchase']['products'])->toHaveCount(1)
                ->and($data['purchase']['products'][0]['name'])->toBe('Premium Widget')
                ->and($data['purchase']['products'][0]['price'])->toBe(9900)
                ->and($data['purchase']['products'][0]['discount'])->toBe(990);

            // Verify reference
            expect($data['reference'])->toBe('ORDER-2025-001');

            // Verify client data
            expect($data['client']['email'])->toBe('customer@example.com')
                ->and($data['client']['full_name'])->toBe('John Customer')
                ->and($data['client']['phone'])->toBe('+60123456789');

            // Verify redirects
            expect($data['success_redirect'])->toBe('https://example.com/success')
                ->and($data['failure_redirect'])->toBe('https://example.com/failed')
                ->and($data['success_callback'])->toBe('https://example.com/webhooks/chip');
        });
    });
});
