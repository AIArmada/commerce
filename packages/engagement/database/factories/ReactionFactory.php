<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Enums\ReactionStatus;
use AIArmada\Engagement\Models\Reaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reaction>
 */
final class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    public function definition(): array
    {
        return [
            'reaction_type' => 'like',
            'status' => ReactionStatus::Active,
            'reacted_at' => now(),
        ];
    }
}
