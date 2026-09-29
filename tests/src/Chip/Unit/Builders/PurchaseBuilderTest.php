<?php

declare(strict_types=1);

use AIArmada\Chip\Builders\PurchaseBuilder;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\RequestClientDetail;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Services\ChipCollectService;
use Akaunting\Money\Money;

describe('PurchaseBuilder', function (): void {
    beforeEach(function (): void {
        config([
            'chip.collect.brand_id' => 'test-brand-id',
        ]);

        $this->service = Mockery::mock(ChipCollectService::class);
        $this->builder = new PurchaseBuilder($this->service);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    it('can build a basic purchase with required fields', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test Product', 5000)
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
            ->addProductCents('Product 1', 1000, 2)
            ->addProductCents('Product 2', 2000, 1, 100, 6.0)
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
            ->addProductCents('Test', 1000)
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
            ->addProductCents('Test', 1000)
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
            ->addProductCents('Test', 1000)
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
            ->addProductCents('Test', 1000)
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
            ->addProductCents('Test', 1000)
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
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->webhook('https://example.com/webhooks/chip')
            ->toArray();

        expect($data)->toHaveKey('success_callback', 'https://example.com/webhooks/chip');
    });

    it('can set reference', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->reference('ORDER-2025-001')
            ->toArray();

        expect($data)->toHaveKey('reference', 'ORDER-2025-001');
    });

    it('can enable send receipt', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->sendReceipt(true)
            ->toArray();

        expect($data)->toHaveKey('send_receipt', true);
    });

    it('can enable pre-authorization', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->preAuthorize(true)
            ->toArray();

        expect($data)->toHaveKey('skip_capture', true);
    });

    it('can force recurring', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->forceRecurring(true)
            ->toArray();

        expect($data)->toHaveKey('force_recurring', true);
    });

    it('can set due date', function (): void {
        $dueDate = now()->addDays(7)->timestamp;

        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->due($dueDate)
            ->toArray();

        expect($data)->toHaveKey('due', $dueDate);
    });

    it('can set notes', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->notes('This is a test purchase')
            ->toArray();

        expect($data['purchase'])->toHaveKey('notes', 'This is a test purchase');
    });

    it('can override brand ID', function (): void {
        $data = $this->builder
            ->brand('custom-brand-id')
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->toArray();

        expect($data)->toHaveKey('brand_id', 'custom-brand-id');
    });

    it('can set client ID', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->clientId('existing-client-id')
            ->toArray();

        expect($data)->toHaveKey('client_id', 'existing-client-id')
            ->and($data)->not->toHaveKey('client');
    });

    it('clears client_id when customer details are set after it', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->clientId('existing-client-id')
            ->customer('test@example.com')
            ->toArray();

        expect($data)->not->toHaveKey('client_id')
            ->and($data)->toHaveKey('client');

        $clientIdFirst = (new PurchaseBuilder($this->service))
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->clientId('existing-client-id')
            ->email('test@example.com')
            ->toArray();

        expect($clientIdFirst)->not->toHaveKey('client_id')
            ->and($clientIdFirst)->toHaveKey('client');

        $billingFirst = (new PurchaseBuilder($this->service))
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->clientId('existing-client-id')
            ->billingAddress('1 Main St', 'KL', '50000')
            ->toArray();

        expect($billingFirst)->not->toHaveKey('client_id')
            ->and($billingFirst)->toHaveKey('client');

        $shippingFirst = (new PurchaseBuilder($this->service))
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->clientId('existing-client-id')
            ->shippingAddress('1 Main St', 'KL', '50000')
            ->toArray();

        expect($shippingFirst)->not->toHaveKey('client_id')
            ->and($shippingFirst)->toHaveKey('client');
    });

    it('supports method chaining for fluent API', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Premium Plan', 9900, 1, 0, 6.0, 'subscription')
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
        $purchaseData = PurchaseData::from([
            'id' => 'test-purchase-id',
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 1000, 'currency' => 'MYR', 'products' => []],
        ]);

        $this->service->shouldReceive('createPurchase')
            ->once()
            ->andReturn($purchaseData);

        $result = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->create();

        expect($result)->toBeInstanceOf(PurchaseData::class)
            ->and($result->id)->toBe('test-purchase-id');
    });

    it('passes the idempotency key separately from the payload', function (): void {
        $purchaseData = PurchaseData::from([
            'id' => 'test-purchase-id',
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 1000, 'currency' => 'MYR', 'products' => []],
        ]);

        $this->service->shouldReceive('createPurchase')
            ->once()
            ->with(Mockery::on(fn ($data) => ! array_key_exists('idempotency_key', $data)), 'idem-key-1')
            ->andReturn($purchaseData);

        $result = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->idempotencyKey('idem-key-1')
            ->create();

        expect($result->id)->toBe('test-purchase-id');
    });

    it('can create purchase using save method alias', function (): void {
        $purchaseData = PurchaseData::from([
            'id' => 'test-purchase-id',
            'client' => ['email' => 'test@example.com'],
            'purchase' => ['total' => 1000, 'currency' => 'MYR', 'products' => []],
        ]);

        $this->service->shouldReceive('createPurchase')
            ->once()
            ->andReturn($purchaseData);

        $result = $this->builder
            ->currency('MYR')
            ->addProductCents('Test', 1000)
            ->email('test@example.com')
            ->save();

        expect($result)->toBeInstanceOf(PurchaseData::class)
            ->and($result->id)->toBe('test-purchase-id');
    });

    it('requires an explicit currency before adding products', function (): void {
        expect(fn () => $this->builder->addProductCents('Product', 1000))
            ->toThrow(ChipValidationException::class, 'Call currency() before adding purchase products.');
    });

    it('accepts fractional product quantities as given', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Half', 100, 0.5)
            ->addProductCents('Frac', 100, '1.555')
            ->addProductCents('Dust', 100, 0.1 + 0.2)
            ->toArray();

        expect($data['purchase']['products'][0]['quantity'])->toBe('0.5')
            ->and($data['purchase']['products'][1]['quantity'])->toBe('1.555')
            ->and($data['purchase']['products'][2]['quantity'])->toBe('0.3');
    });

    it('rejects non-numeric and non-positive product quantities', function (): void {
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, 0))
            ->toThrow(ChipValidationException::class, 'greater than zero');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, -2))
            ->toThrow(ChipValidationException::class, 'greater than zero');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, 'abc'))
            ->toThrow(ChipValidationException::class, 'must be numeric');
    });

    it('rejects quantities with more than 4 decimal places', function (): void {
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, '1.55555'))
            ->toThrow(ChipValidationException::class, 'at most 4 decimal places');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, 1.55555))
            ->toThrow(ChipValidationException::class, 'at most 4 decimal places');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, '1.0000000000000001'))
            ->toThrow(ChipValidationException::class, 'at most 4 decimal places');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, '1.5e-5'))
            ->toThrow(ChipValidationException::class, 'at most 4 decimal places');
    });

    it('rejects out-of-range quantities', function (): void {
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, 1e30))
            ->toThrow(ChipValidationException::class, 'out of range');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, '1e30'))
            ->toThrow(ChipValidationException::class, 'out of range');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('P', 100, '9007199254740993'))
            ->toThrow(ChipValidationException::class, 'out of range');
    });

    it('normalizes integral float quantities to integer strings', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Product', 1000, 2.0)
            ->toArray();

        expect($data['purchase']['products'][0]['quantity'])->toBe('2');
    });

    it('rejects mixed product currencies', function (): void {
        expect(fn () => $this->builder
            ->currency('MYR')
            ->addProductMoney('USD Product', Money::USD(1000)))
            ->toThrow(ChipValidationException::class, 'Product price currency must match the purchase currency.');
    });

    it('nests top-level, purchase, and client setters exactly', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Product', 1000)
            ->email('test@example.com')
            ->issued('2026-01-01')
            ->paymentMethodWhitelist(['fpx', 'visa'])
            ->tags(['promo', 'vip'])
            ->language('en')
            ->debt(-500)
            ->timezone('Asia/Kuala_Lumpur')
            ->emailMessage('Thanks!')
            ->requestClientDetails([RequestClientDetail::EMAIL, 'phone'])
            ->cc(['cc@example.com'])
            ->bcc(['bcc@example.com'])
            ->legalName('Legal Co')
            ->brandName('Brand')
            ->registrationNumber('REG1')
            ->taxNumber('TAX1')
            ->bankAccount('1234567890')
            ->bankCode('MBBEMYKL')
            ->toArray();

        expect($data['issued'])->toBe('2026-01-01')
            ->and($data['payment_method_whitelist'])->toBe(['fpx', 'visa'])
            ->and($data['tags'])->toBe(['promo', 'vip'])
            ->and($data['purchase']['language'])->toBe('en')
            ->and($data['purchase']['debt'])->toBe(-500)
            ->and($data['purchase']['timezone'])->toBe('Asia/Kuala_Lumpur')
            ->and($data['purchase']['email_message'])->toBe('Thanks!')
            ->and($data['purchase']['request_client_details'])->toBe(['email', 'phone'])
            ->and($data['client']['cc'])->toBe(['cc@example.com'])
            ->and($data['client']['bcc'])->toBe(['bcc@example.com'])
            ->and($data['client']['legal_name'])->toBe('Legal Co')
            ->and($data['client']['brand_name'])->toBe('Brand')
            ->and($data['client']['registration_number'])->toBe('REG1')
            ->and($data['client']['tax_number'])->toBe('TAX1')
            ->and($data['client']['bank_account'])->toBe('1234567890')
            ->and($data['client']['bank_code'])->toBe('MBBEMYKL');
    });

    it('accepts all sixteen whitelisted payment methods', function (): void {
        $methods = ['fpx', 'fpx_b2b1', 'crypto_coin', 'dnqr', 'duitnow_qr', 'maestro', 'mastercard', 'mpgs_apple_pay', 'mpgs_google_pay', 'razer_atome', 'razer_grabpay', 'razer_maybankqr', 'razer_shopeepay', 'razer_tng', 'shopee_pay', 'visa'];

        $data = $this->builder->currency('MYR')->paymentMethodWhitelist($methods)->toArray();

        expect($data['payment_method_whitelist'])->toBe($methods);
    });

    it('rejects unknown, empty, and non-string whitelist members', function (): void {
        expect(fn () => $this->builder->paymentMethodWhitelist(['bogus']))
            ->toThrow(ChipValidationException::class, 'Unknown payment method in whitelist.');
        expect(fn () => $this->builder->paymentMethodWhitelist([]))
            ->toThrow(ChipValidationException::class, 'Payment method whitelist cannot be empty.');
        expect(fn () => $this->builder->paymentMethodWhitelist(['fpx', 42]))
            ->toThrow(ChipValidationException::class, 'Unknown payment method in whitelist.');
    });

    it('rejects unknown and duplicate request client details', function (): void {
        expect(fn () => $this->builder->requestClientDetails(['bogus']))
            ->toThrow(ChipValidationException::class, 'Unknown request client details field.');
        expect(fn () => $this->builder->requestClientDetails(['email', 'email']))
            ->toThrow(ChipValidationException::class, 'must not contain duplicates.');
        expect(fn () => $this->builder->requestClientDetails([RequestClientDetail::EMAIL, 'email']))
            ->toThrow(ChipValidationException::class, 'must not contain duplicates.');
    });

    it('rejects blank issued, timezone, tags, and client setters', function (): void {
        expect(fn () => $this->builder->issued('  '))->toThrow(ChipValidationException::class, 'Issued date cannot be blank.');
        expect(fn () => $this->builder->timezone(''))->toThrow(ChipValidationException::class, 'Timezone cannot be blank.');
        expect(fn () => $this->builder->tags(['ok', '  ']))->toThrow(ChipValidationException::class, 'Purchase tags must be non-blank strings.');
        expect(fn () => $this->builder->cc(['a@b.c', '']))->toThrow(ChipValidationException::class, 'Client cc entries must be non-blank strings.');
        expect(fn () => $this->builder->bcc([42]))->toThrow(ChipValidationException::class, 'Client bcc entries must be non-blank strings.');
        expect(fn () => $this->builder->legalName(''))->toThrow(ChipValidationException::class, 'Client legal name cannot be blank.');
        expect(fn () => $this->builder->brandName(' '))->toThrow(ChipValidationException::class, 'Client brand name cannot be blank.');
        expect(fn () => $this->builder->registrationNumber(''))->toThrow(ChipValidationException::class, 'Client registration number cannot be blank.');
        expect(fn () => $this->builder->taxNumber(''))->toThrow(ChipValidationException::class, 'Client tax number cannot be blank.');
        expect(fn () => $this->builder->bankAccount(''))->toThrow(ChipValidationException::class, 'Client bank account cannot be blank.');
        expect(fn () => $this->builder->bankCode(''))->toThrow(ChipValidationException::class, 'Client bank code cannot be blank.');
    });

    it('trims cc and bcc entries on emit', function (): void {
        $data = $this->builder
            ->cc(['  cc@example.com  '])
            ->bcc(["\tbcc@example.com\n"])
            ->toArray();

        expect($data['client']['cc'])->toBe(['cc@example.com'])
            ->and($data['client']['bcc'])->toBe(['bcc@example.com']);
    });

    it('unsets client_id when client setters write', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->clientId('client_123')
            ->legalName('Legal Co')
            ->toArray();

        expect($data)->not->toHaveKey('client_id')
            ->and($data['client']['legal_name'])->toBe('Legal Co');
    });

    it('emits a total price override including zero and rejects negatives', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('Override', 1000, 1, 0, 0.0, null, 250)
            ->addProductCents('Zero', 1000, 1, 0, 0.0, null, 0)
            ->addProductCents('Plain', 1000)
            ->toArray();

        expect($data['purchase']['products'][0]['total_price_override'])->toBe(250)
            ->and($data['purchase']['products'][1]['total_price_override'])->toBe(0)
            ->and($data['purchase']['products'][2])->not->toHaveKey('total_price_override');

        expect(fn () => $this->builder->currency('MYR')->addProductCents('Bad', 1000, 1, 0, 0.0, null, -1))
            ->toThrow(ChipValidationException::class, 'Product total price override cannot be negative.');
    });

    it('emits string tax percents as given and trims whitespace', function (): void {
        $data = $this->builder
            ->currency('MYR')
            ->addProductCents('String', 1000, 1, 0, '6')
            ->addProductCents('Padded', 1000, 1, 0, ' 6.5 ')
            ->addProductCents('Float', 1000, 1, 0, 6.0)
            ->addProductCents('ZeroString', 1000, 1, 0, '0.00')
            ->toArray();

        expect($data['purchase']['products'][0]['tax_percent'])->toBe('6')
            ->and($data['purchase']['products'][1]['tax_percent'])->toBe('6.5')
            ->and($data['purchase']['products'][2]['tax_percent'])->toBe(6.0)
            ->and($data['purchase']['products'][3])->not->toHaveKey('tax_percent');
    });

    it('rejects negative, oversized, and non-numeric tax percents', function (): void {
        expect(fn () => $this->builder->currency('MYR')->addProductCents('Neg', 1000, 1, 0, -1.0))
            ->toThrow(ChipValidationException::class, 'Product tax percent must be a number between 0 and 100.');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('NegStr', 1000, 1, 0, '-1'))
            ->toThrow(ChipValidationException::class, 'Product tax percent must be a number between 0 and 100.');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('Big', 1000, 1, 0, 101.0))
            ->toThrow(ChipValidationException::class, 'Product tax percent must be a number between 0 and 100.');
        expect(fn () => $this->builder->currency('MYR')->addProductCents('Garbage', 1000, 1, 0, 'abc'))
            ->toThrow(ChipValidationException::class, 'Product tax percent must be a number between 0 and 100.');
    });
});
