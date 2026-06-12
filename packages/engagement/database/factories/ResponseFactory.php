<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Response>
 */
final class ResponseFactory extends Factory
{
    protected $model = Response::class;

    public function definition(): array
    {
        return [
            'response_type' => $this->faker->randomElement(['interested', 'going', 'maybe']),
            'status' => Response::STATUS_ACTIVE,
            'visibility' => 'public',
            'responded_at' => now(),
        ];
    }
}
