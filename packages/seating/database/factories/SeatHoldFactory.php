<?php

declare(strict_types=1);

namespace AIArmada\Seating\Database\Factories;

use AIArmada\Seating\Features\Maps\Models\Seat;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SeatHoldFactory extends Factory
{
    protected $model = SeatHold::class;

    public function definition(): array
    {
        return [
            'seat_id' => Seat::factory(),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function converted(): self
    {
        return $this->state(fn (): array => ['converted_at' => now()]);
    }
}
