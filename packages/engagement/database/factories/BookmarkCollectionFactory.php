<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Models\BookmarkCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookmarkCollection>
 */
final class BookmarkCollectionFactory extends Factory
{
    protected $model = BookmarkCollection::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(2) . ' Collection',
            'slug' => Str::slug($this->faker->unique()->sentence(2)),
            'visibility' => 'public',
            'status' => 'active',
        ];
    }
}
