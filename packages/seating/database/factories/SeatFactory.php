<?php

declare(strict_types=1);

namespace AIArmada\Seating\Database\Factories;

use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatSection;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SeatFactory extends Factory
{
    protected $model = Seat::class;

    public function definition(): array
    {
        return [
            'seat_section_id' => SeatSection::factory(),
            'row_label' => chr(rand(65, 90)),
            'row_number' => $this->faker->numberBetween(1, 30),
            'column_number' => $this->faker->numberBetween(1, 50),
            'seat_label' => (string) $this->faker->numberBetween(1, 50),
            'status' => 'available',
            'category' => ['standard', 'vip', 'accessible'][array_rand(['standard', 'vip', 'accessible'])],
        ];
    }

    public function available(): self
    {
        return $this->state(fn () => ['status' => 'available']);
    }

    public function blocked(): self
    {
        return $this->state(fn () => ['status' => 'blocked']);
    }
}
