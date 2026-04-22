<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Integrations\TaxAdapter;
use AIArmada\Checkout\Steps\CalculateTaxStep;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Data\TaxResultData;

use function Pest\Laravel\mock;

it('uses the tax calculator contract for item and shipping tax', function (): void {
    config()->set('checkout.integrations.tax.enabled', true);

    $calculator = mock(TaxCalculatorInterface::class);
    $calculator->shouldReceive('calculateTax')
        ->once()
        ->withArgs(function (int $amount, string $taxClass, ?string $zoneId, array $context): bool {
            expect($amount)->toBe(900)
                ->and($taxClass)->toBe('standard')
                ->and($zoneId)->toBeNull()
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

    $calculator->shouldReceive('calculateShippingTax')
        ->once()
        ->withArgs(function (int $amount, ?string $zoneId, array $context): bool {
            expect($amount)->toBe(200)
                ->and($zoneId)->toBeNull()
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

    $session = CheckoutSession::create([
        'cart_id' => 'tax-calculation-test-cart',
        'subtotal' => 1000,
        'discount_total' => 100,
        'shipping_total' => 200,
        'pricing_data' => [
            'items' => [
                [
                    'quantity' => 1,
                    'line_total' => 1000,
                ],
            ],
        ],
        'cart_snapshot' => [
            'items' => [
                [
                    'quantity' => 1,
                    'price' => 1000,
                    'tax_class' => 'standard',
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