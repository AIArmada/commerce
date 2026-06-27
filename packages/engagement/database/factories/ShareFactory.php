<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Enums\ShareStatus;
use AIArmada\Engagement\Models\Share;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Share>
 */
final class ShareFactory extends Factory
{
    protected $model = Share::class;

    public function definition(): array
    {
        return [
            'status' => ShareStatus::Created,
            'share_url' => $this->faker->url(),
            'share_token' => $this->faker->unique()->lexify('share_????????'),
        ];
    }
}
