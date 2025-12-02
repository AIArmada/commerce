<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->numberBetween(1000, 50000);

        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'name' => $this->faker->words(rand(2, 4), true),
            'sku' => strtoupper($this->faker->bothify('???-####')),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'options' => null,
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(function (array $attributes) use ($product) {
            $quantity = $attributes['quantity'];

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $product->price,
                'total_price' => $quantity * $product->price,
            ];
        });
    }
}
