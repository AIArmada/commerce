<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Integrations\TaxAdapter;
use AIArmada\Checkout\Models\CheckoutSession;

final class CalculateTaxStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly ?TaxAdapter $taxAdapter = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'calculate_tax';
    }

    public function getName(): string
    {
        return 'Calculate Tax';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['calculate_shipping'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        // Skip if tax package is not installed or disabled
        return $this->taxAdapter === null
            || ! config('checkout.integrations.tax.enabled', true);
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if ($this->taxAdapter === null) {
            return $this->skipped('Tax calculation not available');
        }

        // Calculate tax based on shipping address/billing address
        $taxResult = $this->taxAdapter->calculateTax($session);

        $taxData = [
            'breakdown' => $taxResult['breakdown'] ?? [],
            'total' => $taxResult['total'] ?? 0,
            'tax_rate' => $taxResult['rate'] ?? 0,
            'taxable_amount' => $taxResult['taxable_amount'] ?? 0,
            'tax_exempt' => $taxResult['exempt'] ?? false,
            'calculated_at' => now()->toIso8601String(),
        ];

        $taxTotal = $taxResult['total'] ?? 0;

        $session->update([
            'tax_data' => $taxData,
            'tax_total' => $taxTotal,
        ]);

        $session->calculateTotals();
        $session->save();

        return $this->success('Tax calculated', [
            'tax_total' => $taxTotal,
            'tax_rate' => $taxResult['rate'] ?? 0,
        ]);
    }
}
