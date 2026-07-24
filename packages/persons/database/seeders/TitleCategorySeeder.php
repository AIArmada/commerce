<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Seeders;

use AIArmada\Persons\Models\TitleCategory;
use Illuminate\Database\Seeder;

final class TitleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'academic', 'name' => 'Academic Title', 'sort_order' => 10],
            ['code' => 'professional', 'name' => 'Professional Title', 'sort_order' => 20],
        ];

        foreach ($categories as $category) {
            TitleCategory::firstOrCreate(['code' => $category['code']], $category);
        }
    }
}
