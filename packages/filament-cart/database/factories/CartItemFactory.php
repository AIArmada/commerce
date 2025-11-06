<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Database\Factories;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
final class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $this->faker->numberBetween(500, 5000); // cents

        return [
            'cart_id' => Cart::factory(),
            'item_id' => 'product_'.$this->faker->unique()->numberBetween(1, 99999),
            'name' => $this->faker->words(3, true),
            'price' => $price,
            'quantity' => $quantity,
            'attributes' => [
                'color' => $this->faker->safeColorName(),
                'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
            ],
            'conditions' => [],
            'associated_model' => null,
        ];
    }

    public function withConditions(): static
    {
        return $this->state(fn () => [
            'conditions' => [
                [
                    'name' => 'bulk_discount',
                    'type' => 'discount',
                    'target' => 'price',
                    'value' => '-10%',
                    'order' => 1,
                    'attributes' => [],
                ],
            ],
        ]);
    }
}
