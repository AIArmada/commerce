<?php

declare(strict_types=1);

namespace AIArmada\Cart\Database\Factories;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Snapshots\CartSnapshotItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartSnapshotItem>
 */
final class CartSnapshotItemFactory extends Factory
{
    protected $model = CartSnapshotItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $this->faker->numberBetween(500, 5000); // cents

        return [
            'cart_id' => CartSnapshot::factory(),
            'item_id' => 'product_' . $this->faker->unique()->numberBetween(1, 99999),
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
                    'target' => 'items@item_discount/per-item',
                    'target_definition' => ConditionTarget::from('items@item_discount/per-item')->toArray(),
                    'value' => '-10%',
                    'order' => 1,
                    'attributes' => [],
                ],
            ],
        ]);
    }
}
