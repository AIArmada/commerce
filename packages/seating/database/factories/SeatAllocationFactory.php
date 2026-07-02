<?php

declare(strict_types=1);

namespace AIArmada\Seating\Database\Factories;

use AIArmada\Seating\Models\Seat;
use AIArmada\Seating\Models\SeatAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class SeatAllocationFactory extends Factory
{
    protected $model = SeatAllocation::class;

    public function definition(): array
    {
        return [
            'seat_id' => Seat::factory(),
            'allocated_to_type' => 'pass',
            'allocated_to_id' => (string) Str::orderedUuid(),
            'state' => 'active',
            'allocated_at' => now(),
        ];
    }

    public function released(): self
    {
        return $this->state(fn (): array => [
            'state' => 'released',
            'released_at' => now(),
        ]);
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => [
            'state' => 'revoked',
            'revoked_at' => now(),
        ]);
    }
}
