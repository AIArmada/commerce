<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventTicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTicketType>
 */
final class EventTicketTypeFactory extends Factory
{
    protected $model = EventTicketType::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->word() . ' Ticket',
            'code' => $this->faker->unique()->lexify('ticket_????'),
            'access_type' => 'general',
            'price' => $this->faker->numberBetween(0, 10000),
            'currency' => 'USD',
            'admits_quantity' => 1,
            'status' => 'active',
            'visibility' => 'public',
        ];
    }

    public function freeTicket(): static
    {
        return $this->state(fn () => ['price' => 0]);
    }
}
