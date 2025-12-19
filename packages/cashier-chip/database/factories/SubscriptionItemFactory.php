<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Database\Factories;

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionItem>
 */
final class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => null,
            'owner_id' => null,
            'subscription_id' => Subscription::factory(),
            'chip_id' => 'si_' . Str::random(40),
            'chip_product' => 'prod_' . Str::random(24),
            'chip_price' => 'price_' . Str::random(24),
            'quantity' => 1,
            'unit_amount' => $this->faker->numberBetween(1000, 10000),
        ];
    }

    /**
     * Set a specific price identifier.
     */
    public function withPrice(string $price): static
    {
        return $this->state([
            'chip_price' => $price,
        ]);
    }

    /**
     * Set a specific product identifier.
     */
    public function withProduct(string $product): static
    {
        return $this->state([
            'chip_product' => $product,
        ]);
    }

    /**
     * Set a specific quantity.
     */
    public function quantity(int $quantity): static
    {
        return $this->state([
            'quantity' => $quantity,
        ]);
    }

    /**
     * Set a specific unit amount in cents.
     */
    public function unitAmount(int $amount): static
    {
        return $this->state([
            'unit_amount' => $amount,
        ]);
    }

    /**
     * Attach to a specific subscription.
     */
    public function forSubscription(Subscription $subscription): static
    {
        return $this->state([
            'subscription_id' => $subscription->id,
        ]);
    }
}
