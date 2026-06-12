<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Database\Factories;

use AIArmada\Engagement\Models\BookmarkCollectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookmarkCollectionItem>
 */
final class BookmarkCollectionItemFactory extends Factory
{
    protected $model = BookmarkCollectionItem::class;

    public function definition(): array
    {
        return [
            'added_at' => now(),
        ];
    }
}
