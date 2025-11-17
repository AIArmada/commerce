<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Database\Factories;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<Cart>
 */
final class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        [$items, $quantity, $subtotal] = $this->generateItems();
        $conditions = $this->generateConditions();

        return [
            'identifier' => $this->faker->uuid(),
            'instance' => Arr::random(['default', 'wishlist', 'comparison', 'quote']),
            'items' => $items,
            'conditions' => $conditions,
            'metadata' => $this->generateMetadata(),
            'items_count' => count($items),
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'savings' => 0,
            'currency' => mb_strtoupper(config('cart.money.default_currency', 'USD')),
        ];
    }

    public function empty(): static
    {
        return $this->state(fn () => [
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'items_count' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'total' => 0,
            'savings' => 0,
        ]);
    }

    public function instance(string $instance): static
    {
        return $this->state(fn () => ['instance' => $instance]);
    }

    public function withIdentifier(string $identifier): static
    {
        return $this->state(fn () => ['identifier' => $identifier]);
    }

    public function withManyItems(int $count = 10): static
    {
        return $this->state(function () use ($count) {
            [$items, $quantity, $subtotal] = $this->generateItems($count);

            return [
                'items' => $items,
                'items_count' => count($items),
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ];
        });
    }

    public function expensive(): static
    {
        return $this->state(function () {
            [$items, $quantity, $subtotal] = $this->generateItems(null, true);

            return [
                'items' => $items,
                'items_count' => count($items),
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ];
        });
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: int}
     */
    private function generateItems(?int $count = null, bool $expensive = false): array
    {
        $count ??= $this->faker->numberBetween(1, 4);

        $items = [];
        $quantity = 0;
        $subtotal = 0;

        for ($i = 0; $i < $count; $i++) {
            $lineQuantity = $this->faker->numberBetween(1, 5);
            $price = $expensive
                ? $this->faker->numberBetween(5000, 50000) // cents, expensive items
                : $this->faker->numberBetween(500, 5000); // cents

            $items[] = [
                'id' => 'product_'.$this->faker->unique()->numberBetween(1, 99999),
                'name' => $this->faker->words(3, true),
                'price' => $price,
                'quantity' => $lineQuantity,
                'attributes' => [
                    'color' => $this->faker->safeColorName(),
                    'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                ],
            ];

            $quantity += $lineQuantity;
            $subtotal += $price * $lineQuantity;
        }

        return [$items, $quantity, $subtotal];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function generateConditions(): array
    {
        if ($this->faker->boolean(70)) {
            return [[
                'name' => 'Sales Tax',
                'type' => 'tax',
                'target' => 'cart@grand_total/aggregate',
                'target_definition' => ConditionTarget::from('cart@grand_total/aggregate')->toArray(),
                'value' => '+600',
                'order' => 0,
            ]];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function generateMetadata(): array
    {
        return [
            'session_id' => $this->faker->uuid(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
