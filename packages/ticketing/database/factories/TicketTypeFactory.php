<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
final class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'ticketable_type' => 'workshop',
            'ticketable_id' => $this->faker->uuid(),
            'name' => $this->faker->words(3, true),
            'code' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'access_type' => 'general',
            'price' => $this->faker->numberBetween(0, 100000),
            'currency' => 'MYR',
            'admits_quantity' => 1,
            'min_quantity' => 1,
            'max_quantity' => null,
            'status' => 'active',
            'visibility' => 'public',
            'sales_starts_at' => null,
            'sales_ends_at' => null,
            'sort_order' => 0,
        ];
    }
}
