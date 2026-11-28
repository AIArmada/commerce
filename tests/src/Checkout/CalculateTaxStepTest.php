<?php

declare(strict_types=1);

use AIArmada\Checkout\Integrations\TaxAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\CalculateTaxStep;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;
use Mockery\Expectation;

use function Pest\Laravel\mock;

it('uses the tax calculator contract for item and shipping tax', function (): void {
    config()->set('checkout.integrations.tax.enabled', true);

    $calculator = mock(TaxCalculatorInterface::class);
    /** @var Expectation $expectation */
    $expectation = $calculator->shouldReceive('calculateTax');
    $expectation->once()
        ->withArgs(function (int $amount, string $taxClass, ?string $zoneId, array $context): bool {
            expect($amount)->toBe(900)
                ->and($taxClass)->toBe('reduced')
                ->and($zoneId)->toBeNull()
                ->and($context['currency'])->toBe('MYR')
                ->and($context['shipping_address']['country'])->toBe('MY')
                ->and($context['billing_address']['country'])->toBe('MY');

            return true;
        })
        ->andReturn(new TaxResultData(
            taxAmount: 54,
            rateId: 'tax-rate-standard',
            rateName: 'Standard Tax',
            ratePercentage: 600,
            zoneId: 'tax-zone-my',
            zoneName: 'Malaysia',
            breakdown: [
                ['name' => 'Standard Tax', 'rate' => 600, 'amount' => 54, 'is_compound' => false],
            ],
        ));

    /** @var Expectation $shippingExpectation */
    $shippingExpectation = $calculator->shouldReceive('calculateShippingTax');
    $shippingExpectation->once()
        ->withArgs(function (int $amount, ?string $zoneId, array $context): bool {
            expect($amount)->toBe(200)
                ->and($zoneId)->toBeNull()
                ->and($context['currency'])->toBe('MYR')
                ->and($context['shipping_address']['country'])->toBe('MY');

            return true;
        })
        ->andReturn(new TaxResultData(
            taxAmount: 12,
            rateId: 'tax-rate-shipping',
            rateName: 'Shipping Tax',
            ratePercentage: 600,
            zoneId: 'tax-zone-my',
            zoneName: 'Malaysia',
            breakdown: [
                ['name' => 'Shipping Tax', 'rate' => 600, 'amount' => 12, 'is_compound' => false],
            ],
        ));

    app()->instance(TaxCalculatorInterface::class, $calculator);

    $session = CheckoutSession::forceCreate([
        'cart_id' => 'tax-calculation-test-cart',
        'subtotal' => 1000,
        'discount_total' => 100,
        'shipping_total' => 200,
        'pricing_data' => [
            'items' => [
                [
                    'item_id' => 'line-1',
                    'quantity' => 1,
                    'line_total' => 1000,
                ],
            ],
        ],
        'cart_snapshot' => [
            'items' => [
                [
                    'id' => 'line-1',
                    'quantity' => 1,
                    'price' => 1000,
                    'tax_class' => 'reduced',
                ],
            ],
        ],
        'shipping_data' => [
            'country' => 'MY',
            'state' => 'Selangor',
            'postcode' => '40000',
        ],
        'billing_data' => [
            'country' => 'MY',
            'state' => 'Selangor',
            'postcode' => '40000',
        ],
    ]);

    $step = new CalculateTaxStep(new TaxAdapter);
    $result = $step->handle($session);

    $session->refresh();

    expect($result->isSuccessful())->toBeTrue()
        ->and($session->tax_total)->toBe(66)
        ->and($session->tax_data['taxable_amount'])->toBe(1100)
        ->and($session->tax_data['breakdown'])->toHaveCount(2);
});

it('does not calculate item tax for a non-taxable cart snapshot line', function (): void {
    $calculator = mock(TaxCalculatorInterface::class);
    $calculator->shouldNotReceive('calculateTax');
    $calculator->shouldNotReceive('calculateShippingTax');

    $session = CheckoutSession::forceCreate([
        'cart_id' => 'non-taxable-snapshot-cart',
        'subtotal' => 1000,
        'cart_snapshot' => [
            'items' => [['quantity' => 1, 'price' => 1000, 'is_taxable' => false]],
        ],
    ]);

    $result = (new TaxAdapter)->calculateTax($session);

    expect($result['total'])->toBe(0)
        ->and($result['taxable_amount'])->toBe(0);
});

it('matches pricing to the correct taxable cart line regardless of row order', function (): void {
    $calculator = mock(TaxCalculatorInterface::class);
    $calculator->shouldReceive('calculateTax')->once()
        ->withArgs(fn (int $amount, string $taxClass, ?string $zoneId, array $context): bool => $amount === 800 && $taxClass === 'reduced')
        ->andReturn(new TaxResultData(40, 'rate', 'Reduced', 500, 'zone', 'Malaysia'));
    $calculator->shouldNotReceive('calculateShippingTax');

    $session = new CheckoutSession;
    $session->forceFill([
        'subtotal' => 2800,
        'cart_snapshot' => ['items' => [
            ['id' => 'exempt-line', 'price' => 2000, 'attributes' => ['is_taxable' => false]],
            ['id' => 'taxable-line', 'price' => 1000, 'attributes' => ['tax_class' => 'reduced']],
        ]],
        'pricing_data' => ['items' => [
            ['item_id' => 'taxable-line', 'line_total' => 800],
            ['item_id' => 'exempt-line', 'line_total' => 2000],
        ]],
    ]);

    $result = (new TaxAdapter)->calculateTax($session);

    expect($result['total'])->toBe(40)
        ->and($result['taxable_amount'])->toBe(800);
});

it('rejects mismatched pricing identities before tax calculation', function (): void {
    $calculator = mock(TaxCalculatorInterface::class);
    $calculator->shouldNotReceive('calculateTax');

    $session = new CheckoutSession;
    $session->forceFill([
        'cart_snapshot' => ['items' => [['id' => 'actual-line', 'price' => 1000]]],
        'pricing_data' => ['items' => [['item_id' => 'unknown-line', 'line_total' => 1000]]],
    ]);

    expect(fn () => (new TaxAdapter)->calculateTax($session))
        ->toThrow(InvalidArgumentException::class, 'Checkout pricing does not match the cart snapshot.');
});

it('does not add already included tax to the checkout payable total', function (): void {
    $calculator = mock(TaxCalculatorInterface::class);
    $calculator->shouldReceive('calculateTax')->once()
        ->andReturn(new TaxResultData(100, 'rate', 'Inclusive', 1000, 'zone', 'Malaysia', includedInPrice: true));
    $calculator->shouldReceive('calculateShippingTax')->once()
        ->andReturn(new TaxResultData(20, 'shipping-rate', 'Shipping', 1000, 'zone', 'Malaysia'));

    $session = CheckoutSession::forceCreate([
        'cart_id' => 'inclusive-tax-checkout',
        'subtotal' => 1100,
        'shipping_total' => 200,
        'cart_snapshot' => ['items' => [['id' => 'line-1', 'quantity' => 1, 'price' => 1100]]],
    ]);

    $result = (new CalculateTaxStep(new TaxAdapter))->handle($session);
    $session->refresh();

    expect($result->isSuccessful())->toBeTrue()
        ->and($session->tax_total)->toBe(20)
        ->and($session->tax_data['total'])->toBe(120)
        ->and($session->tax_data['included_total'])->toBe(100)
        ->and($session->tax_data['additional_total'])->toBe(20)
        ->and($session->grand_total)->toBe(1320);
});
