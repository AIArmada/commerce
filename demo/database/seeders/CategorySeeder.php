<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and gadgets',
                'children' => [
                    ['name' => 'Smartphones', 'slug' => 'smartphones', 'description' => 'Mobile phones and accessories'],
                    ['name' => 'Laptops', 'slug' => 'laptops', 'description' => 'Notebook computers'],
                    ['name' => 'Audio', 'slug' => 'audio', 'description' => 'Headphones, speakers, and audio equipment'],
                ],
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'description' => 'Clothing and accessories',
                'children' => [
                    ['name' => "Men's Clothing", 'slug' => 'mens-clothing', 'description' => 'Apparel for men'],
                    ['name' => "Women's Clothing", 'slug' => 'womens-clothing', 'description' => 'Apparel for women'],
                    ['name' => 'Shoes', 'slug' => 'shoes', 'description' => 'Footwear for all'],
                ],
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'description' => 'Home decor and furniture',
                'children' => [
                    ['name' => 'Furniture', 'slug' => 'furniture', 'description' => 'Tables, chairs, and storage'],
                    ['name' => 'Kitchen', 'slug' => 'kitchen', 'description' => 'Kitchen appliances and tools'],
                    ['name' => 'Decor', 'slug' => 'decor', 'description' => 'Decorative items'],
                ],
            ],
        ];

        $sortOrder = 0;
        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $parent = Category::create([
                ...$categoryData,
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);

            $childOrder = 0;
            foreach ($children as $childData) {
                Category::create([
                    ...$childData,
                    'parent_id' => $parent->id,
                    'is_active' => true,
                    'sort_order' => $childOrder++,
                ]);
            }
        }
    }
}
