<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassHolder>
 */
final class PassHolderFactory extends Factory
{
    protected $model = PassHolder::class;

    public function definition(): array
    {
        return [
            'pass_id' => Pass::factory(),
            'holder_type' => null,
            'holder_id' => null,
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'is_current' => true,
            'transferred_at' => null,
        ];
    }
}
