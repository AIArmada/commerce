<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Models\Bookmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
final class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    public function definition(): array
    {
        return [
            'status' => BookmarkStatus::Active,
            'bookmarked_at' => now(),
        ];
    }
}
