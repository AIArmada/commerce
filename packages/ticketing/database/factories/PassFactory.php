<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pass>
 */
final class PassFactory extends Factory
{
    protected $model = Pass::class;

    public function definition(): array
    {
        return [
            'ticketable_type' => config('ticketing.testing.ticketable_type', 'workshop'),
            'ticketable_id' => config('ticketing.testing.ticketable_id', $this->faker->uuid()),
            'ticket_type_id' => TicketType::factory(),
            'pass_no' => 'PASS-' . mb_strtoupper($this->faker->unique()->bothify('??????')),
            'qr_code' => (string) $this->faker->uuid(),
            'barcode' => $this->faker->bothify(str_repeat('?', 16)),
            'status' => 'issued',
            'issued_at' => now(),
            'metadata' => null,
        ];
    }
}
