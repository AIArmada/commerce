<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\EventTicketTypeSeatingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTicketTypeSeatingOption>
 */
final class EventTicketTypeSeatingOptionFactory extends Factory
{
    protected $model = EventTicketTypeSeatingOption::class;

    public function definition(): array
    {
        return [
            'included_quantity' => 1,
            'allowed_quantity' => 1,
        ];
    }
}
