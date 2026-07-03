<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeSeatingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTypeSeatingOption>
 */
class TicketTypeSeatingOptionFactory extends Factory
{
    protected $model = TicketTypeSeatingOption::class;

    public function definition(): array
    {
        return [
            'ticket_type_id' => TicketType::factory(),
            'seat_section_id' => null,
            'seat_category' => null,
            'included_quantity' => null,
            'allowed_quantity' => null,
            'metadata' => [],
        ];
    }
}
