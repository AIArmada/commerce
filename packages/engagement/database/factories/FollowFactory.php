<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Enums\FollowStatus;
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
            'status' => FollowStatus::Active,
            'followed_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => FollowStatus::Active]);
    }

    public function muted(): static
    {
        return $this->state(fn () => ['status' => FollowStatus::Muted, 'muted_at' => now()]);
    }
}
