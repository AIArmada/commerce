<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Database\Factories;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\AIArmada\FilamentCart\Models\CartCondition>
 */
final class CartConditionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CartCondition::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['discount', 'tax', 'fee', 'shipping']);

        return [
            'cart_id' => Cart::factory(),
            'cart_item_id' => null, // Cart-level by default
            'name' => $this->faker->words(2, true).'_'.$type,
            'type' => $type,
            'target' => $this->faker->randomElement(['subtotal', 'total', 'price']),
            'value' => $this->generateValue($type),
            'order' => $this->faker->numberBetween(1, 10),
            'attributes' => $this->generateAttributes($type),
            'item_id' => null,
            'is_global' => false,
        ];
    }

    /**
     * State for discount conditions.
     */
    public function discount(): static
    {
        return $this->state(fn () => [
            'type' => 'discount',
            'value' => '-'.$this->faker->numberBetween(5, 50).'%',
            'attributes' => [
                'description' => 'Promotional discount',
                'promo_code' => $this->faker->regexify('[A-Z]{4}[0-9]{2}'),
            ],
        ]);
    }

    /**
     * State for tax conditions.
     */
    public function tax(): static
    {
        return $this->state(fn () => [
            'type' => 'tax',
            'value' => $this->faker->randomFloat(2, 5, 15).'%',
            'attributes' => [
                'tax_type' => $this->faker->randomElement(['VAT', 'Sales Tax', 'GST']),
                'tax_region' => $this->faker->stateAbbr(), // @phpstan-ignore method.notFound
            ],
        ]);
    }

    /**
     * State for fee conditions.
     */
    public function fee(): static
    {
        return $this->state(fn () => [
            'type' => 'fee',
            'value' => '+'.$this->faker->numberBetween(100, 2500),
            'attributes' => [
                'fee_type' => $this->faker->randomElement(['Processing Fee', 'Service Fee', 'Handling Fee']),
                'description' => 'Additional processing fee',
            ],
        ]);
    }

    /**
     * State for shipping conditions.
     */
    public function shipping(): static
    {
        return $this->state(fn () => [
            'type' => 'shipping',
            'value' => '+'.$this->faker->numberBetween(500, 5000),
            'attributes' => [
                'shipping_method' => $this->faker->randomElement(['Standard', 'Express', 'Overnight']),
                'carrier' => $this->faker->randomElement(['UPS', 'FedEx', 'USPS', 'DHL']),
                'estimated_days' => $this->faker->numberBetween(1, 14),
            ],
        ]);
    }

    /**
     * State for item-level conditions.
     */
    public function itemLevel(): static
    {
        return $this->state(fn () => [
            'cart_item_id' => CartItem::factory(),
            'item_id' => $this->faker->uuid(),
        ]);
    }

    /**
     * State for cart-level conditions.
     */
    public function cartLevel(): static
    {
        return $this->state(fn () => [
            'cart_item_id' => null,
            'item_id' => null,
        ]);
    }

    public function global(): static
    {
        return $this->state(fn () => ['is_global' => true]);
    }

    /**
     * State for percentage-based conditions.
     */
    public function percentage(): static
    {
        return $this->state(fn () => [
            'value' => $this->faker->randomElement(['-', '+']).$this->faker->numberBetween(5, 50).'%',
        ]);
    }

    /**
     * State for fixed amount conditions.
     */
    public function fixedAmount(): static
    {
        return $this->state(fn () => [
            'value' => $this->faker->randomElement(['-', '+']).$this->faker->numberBetween(100, 10000),
        ]);
    }

    /**
     * Generate value based on condition type.
     */
    private function generateValue(string $type): string
    {
        return match ($type) {
            'discount' => '-'.$this->faker->numberBetween(5, 50).'%',
            'tax' => $this->faker->randomFloat(2, 5, 15).'%',
            'fee' => '+'.$this->faker->numberBetween(100, 2500),
            'shipping' => '+'.$this->faker->numberBetween(500, 5000),
            default => '+'.$this->faker->numberBetween(100, 10000),
        };
    }

    /**
     * Generate attributes based on condition type.
     *
     * @return array<string, mixed>
     */
    private function generateAttributes(string $type): array
    {
        return match ($type) {
            'discount' => [
                'description' => 'Promotional discount',
                'promo_code' => $this->faker->regexify('[A-Z]{4}[0-9]{2}'),
            ],
            'tax' => [
                'tax_type' => $this->faker->randomElement(['VAT', 'Sales Tax', 'GST']),
                'tax_region' => $this->faker->stateAbbr(), // @phpstan-ignore method.notFound
            ],
            'fee' => [
                'fee_type' => $this->faker->randomElement(['Processing Fee', 'Service Fee']),
                'description' => 'Additional fee',
            ],
            'shipping' => [
                'shipping_method' => $this->faker->randomElement(['Standard', 'Express']),
                'carrier' => $this->faker->randomElement(['UPS', 'FedEx', 'USPS']),
            ],
            default => [],
        };
    }
}
