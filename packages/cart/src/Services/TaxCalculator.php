<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Target;
use Akaunting\Money\Money;

/**
 * Built-in tax calculator for common tax scenarios.
 *
 * Provides simple flat-rate and tiered tax calculations. For complex
 * tax scenarios (multi-jurisdiction, product-specific rates), use a
 * dedicated tax service like TaxJar, Avalara, or Vertex.
 */
final class TaxCalculator
{
    /**
     * @var array<string, array{rate: float, name: string, inclusive: bool}>
     */
    private array $rates = [];

    /**
     * @var array<string, float>
     */
    private array $regionRates = [];

    /**
     * @var array<string, float>
     */
    private array $categoryRates = [];

    private string $defaultCategory = 'standard';

    public function __construct(
        private float $defaultRate = 0.0,
        private ?string $defaultRegion = null,
        private bool $pricesIncludeTax = false,
    ) {}

    /**
     * Create common tax presets.
     */
    public static function withDefaults(): self
    {
        return (new self)
            ->registerRate('MY-SST', 8.0, 'Sales & Service Tax (SST)')
            ->registerRate('MY-SST-6', 6.0, 'Service Tax')
            ->registerRate('SG-GST', 9.0, 'Goods & Services Tax (GST)')
            ->registerRate('AU-GST', 10.0, 'Goods & Services Tax (GST)')
            ->registerRate('US-DEFAULT', 0.0, 'No Tax')
            ->registerRate('UK-VAT', 20.0, 'VAT', inclusive: true)
            ->registerRate('UK-VAT-REDUCED', 5.0, 'VAT (Reduced)', inclusive: true)
            ->registerRate('EU-VAT-STANDARD', 21.0, 'VAT', inclusive: true)
            ->registerCategoryRate('standard', 8.0)
            ->registerCategoryRate('food', 0.0)
            ->registerCategoryRate('digital', 8.0)
            ->registerCategoryRate('exempt', 0.0);
    }

    /**
     * Get the default tax rate.
     */
    public function getDefaultRate(): float
    {
        return $this->defaultRate;
    }

    /**
     * Get the default region.
     */
    public function getDefaultRegion(): ?string
    {
        return $this->defaultRegion;
    }

    /**
     * Check if prices include tax.
     */
    public function pricesIncludeTax(): bool
    {
        return $this->pricesIncludeTax;
    }

    /**
     * Set a region-specific tax rate.
     *
     * @param  string  $region  Region code (e.g., 'MY', 'SG', 'US-CA')
     * @param  float  $rate  Tax rate as decimal (e.g., 0.08 for 8%)
     */
    public function setRegionRate(string $region, float $rate): self
    {
        $this->regionRates[$region] = $rate;

        return $this;
    }

    /**
     * Get the tax rate for a region.
     *
     * @param  string  $region  Region code
     * @return float Tax rate as decimal
     */
    public function getRegionRate(string $region): float
    {
        return $this->regionRates[$region] ?? $this->defaultRate;
    }

    /**
     * Calculate tax for a Money amount.
     *
     * @param  Money  $amount  The amount to calculate tax on
     * @param  string|null  $region  Optional region code
     * @return Money The calculated tax amount
     */
    public function calculateTax(Money $amount, ?string $region = null): Money
    {
        $region = $region ?? $this->defaultRegion;
        $rate = $region !== null ? $this->getRegionRate($region) : $this->defaultRate;

        if ($rate === 0.0) {
            return $amount->multiply(0);
        }

        if ($this->pricesIncludeTax) {
            // Extract tax from inclusive price: tax = price - (price / (1 + rate))
            $divisor = 1 + $rate;
            $netAmount = (int) round($amount->getAmount() / $divisor);
            $taxAmount = $amount->getAmount() - $netAmount;

            return new Money($taxAmount, $amount->getCurrency());
        }

        // Calculate tax on exclusive price: tax = price * rate
        $taxAmount = (int) round($amount->getAmount() * $rate);

        return new Money($taxAmount, $amount->getCurrency());
    }

    /**
     * Register a tax rate with name and description.
     *
     * @param  string  $code  Tax code (e.g., 'MY-SST', 'US-CA', 'VAT')
     * @param  float  $rate  Tax rate as percentage (e.g., 8.0 for 8%)
     * @param  string  $name  Display name (e.g., 'Sales & Service Tax')
     * @param  bool  $inclusive  Whether prices already include tax
     */
    public function registerRate(
        string $code,
        float $rate,
        string $name,
        bool $inclusive = false
    ): self {
        $this->rates[$code] = [
            'rate' => $rate,
            'name' => $name,
            'inclusive' => $inclusive,
        ];

        // Also set as region rate (converting percentage to decimal)
        $this->regionRates[$code] = $rate / 100;

        return $this;
    }

    /**
     * Register category-specific tax rates.
     *
     * @param  string  $category  Category name (e.g., 'food', 'digital', 'exempt')
     * @param  float  $rate  Tax rate for this category
     */
    public function registerCategoryRate(string $category, float $rate): self
    {
        $this->categoryRates[$category] = $rate;

        return $this;
    }

    /**
     * Set the default tax category.
     */
    public function setDefaultCategory(string $category): self
    {
        $this->defaultCategory = $category;

        return $this;
    }

    /**
     * Get registered rate info.
     *
     * @return array{rate: float, name: string, inclusive: bool}|null
     */
    public function getRate(string $code): ?array
    {
        return $this->rates[$code] ?? null;
    }

    /**
     * Calculate tax for a cart and apply as condition.
     *
     * @param  Cart  $cart  The cart to calculate tax for
     * @param  string  $rateCode  The tax rate code to use
     * @param  string|null  $conditionName  Custom condition name
     * @return CartCondition|null The tax condition, or null if rate not found
     */
    public function applyToCart(
        Cart $cart,
        string $rateCode,
        ?string $conditionName = null
    ): ?CartCondition {
        $rateInfo = $this->rates[$rateCode] ?? null;

        if ($rateInfo === null) {
            return null;
        }

        $condition = $this->createTaxCondition(
            name: $conditionName ?? $rateInfo['name'],
            rate: $rateInfo['rate'],
            inclusive: $rateInfo['inclusive']
        );

        $cart->addCondition($condition);

        return $condition;
    }

    /**
     * Calculate tax with category-based rates per item.
     *
     * Uses item's `tax_category` attribute to determine rate.
     *
     * @param  Cart  $cart  The cart to calculate tax for
     * @param  string  $conditionName  Name for the tax condition
     * @param  float  $defaultRate  Default rate if category not found
     */
    public function applyWithCategories(
        Cart $cart,
        string $conditionName = 'Tax',
        float $defaultRate = 0.0
    ): CartCondition {
        $taxableAmount = 0;

        foreach ($cart->getItems() as $item) {
            $category = $item->getAttribute('tax_category') ?? $this->defaultCategory;
            $rate = $this->categoryRates[$category] ?? $defaultRate;

            if ($rate > 0 && ($item->getAttribute('taxable') ?? true)) {
                $itemTotal = $item->price * $item->quantity;
                $taxableAmount += $itemTotal * ($rate / 100);
            }
        }

        $condition = new CartCondition(
            name: $conditionName,
            type: 'tax',
            value: $taxableAmount,
            target: Target::cart()
                ->phase(ConditionPhase::TAX)
                ->applyAggregate()
                ->build(),
            order: ConditionPhase::TAX->order()
        );

        $cart->addCondition($condition);

        return $condition;
    }

    /**
     * Create a simple tax condition.
     *
     * @param  string  $name  Tax name
     * @param  float  $rate  Tax rate as percentage
     * @param  bool  $inclusive  Whether prices include tax
     */
    public function createTaxCondition(
        string $name,
        float $rate,
        bool $inclusive = false
    ): CartCondition {
        $value = $inclusive
            ? 0  // No additional tax for inclusive pricing
            : "+{$rate}%";

        return new CartCondition(
            name: $name,
            type: 'tax',
            value: $value,
            target: Target::cart()
                ->phase(ConditionPhase::TAX)
                ->applyAggregate()
                ->build(),
            order: ConditionPhase::TAX->order(),
            attributes: [
                'rate' => $rate,
                'inclusive' => $inclusive,
            ]
        );
    }

    /**
     * Calculate tax for checkout display.
     *
     * @return array{amount: int, rate: float, name: string}
     */
    public function calculateForDisplay(Cart $cart, string $rateCode): array
    {
        $rateInfo = $this->rates[$rateCode] ?? null;

        if ($rateInfo === null) {
            return ['amount' => 0, 'rate' => 0.0, 'name' => 'No Tax'];
        }

        $subtotal = $cart->getRawSubtotal();
        $taxAmount = (int) round($subtotal * ($rateInfo['rate'] / 100));

        return [
            'amount' => $taxAmount,
            'rate' => $rateInfo['rate'],
            'name' => $rateInfo['name'],
        ];
    }
}
