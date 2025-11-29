<?php

declare(strict_types=1);

namespace AIArmada\Stock\Database\Factories;

use AIArmada\Stock\Models\StockTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    protected $model = StockTransaction::class;

    public function definition(): array
    {
        return [
            'stockable_type' => 'App\\Models\\Product',
            'stockable_id' => $this->faker->uuid(),
            'user_id' => null,
            'quantity' => $this->faker->numberBetween(1, 100),
            'type' => $this->faker->randomElement(['in', 'out']),
            'reason' => $this->faker->randomElement(['sale', 'adjustment', 'return']),
            'note' => $this->faker->sentence(),
            'transaction_date' => now(),
        ];
    }
}
