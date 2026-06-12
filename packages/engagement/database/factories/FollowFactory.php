<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Models\Follow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
final class FollowFactory extends Factory
{
    protected $model = Follow::class;

    public function definition(): array
    {
        return [
            'status' => Follow::STATUS_ACTIVE,
            'followed_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Follow::STATUS_ACTIVE]);
    }

    public function muted(): static
    {
        return $this->state(fn () => ['status' => Follow::STATUS_MUTED, 'muted_at' => now()]);
    }
}
