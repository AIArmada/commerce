<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Database\Seeders;

use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Database\Seeder;

final class CartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some default carts
        Cart::factory()->count(5)->create();

        // Create some wishlist carts
        Cart::factory()->count(3)->instance('wishlist')->create();

        // Create some comparison carts
        Cart::factory()->count(2)->instance('comparison')->create();

        // Create some quote carts
        Cart::factory()->count(2)->instance('quote')->create();

        // Create some empty carts
        Cart::factory()->count(3)->empty()->create();

        // Create some carts with many items
        Cart::factory()->count(2)->withManyItems(8)->create();

        // Create some expensive carts
        Cart::factory()->count(2)->expensive()->create();

        // Create specific test carts
        Cart::factory()->withIdentifier('demo-cart-123')->create([
            'items' => [
                [
                    'id' => 'laptop-001',
                    'name' => 'MacBook Pro 16"',
                    'price' => 2499.99,
                    'quantity' => 1,
                    'attributes' => [
                        'color' => 'Space Gray',
                        'storage' => '512GB',
                        'memory' => '16GB',
                    ],
                ],
                [
                    'id' => 'mouse-001',
                    'name' => 'Magic Mouse',
                    'price' => 79.99,
                    'quantity' => 1,
                    'attributes' => [
                        'color' => 'White',
                        'type' => 'Wireless',
                    ],
                ],
            ],
            'conditions' => [
                [
                    'name' => 'Student Discount',
                    'type' => 'discount',
                    'value' => 200.00,
                    'description' => '10% student discount',
                ],
                [
                    'name' => 'Sales Tax',
                    'type' => 'tax',
                    'value' => 178.89,
                    'description' => '8.25% CA sales tax',
                ],
            ],
            'metadata' => [
                'user_id' => 1,
                'currency' => 'USD',
                'notes' => 'Demo cart for testing purposes',
                'created_from' => 'web',
            ],
        ]);

        Cart::factory()->withIdentifier('abandoned-cart-456')->create([
            'items' => [
                [
                    'id' => 'shoes-001',
                    'name' => 'Running Shoes',
                    'price' => 129.99,
                    'quantity' => 1,
                    'attributes' => [
                        'size' => '10',
                        'color' => 'Black',
                        'brand' => 'Nike',
                    ],
                ],
            ],
            'metadata' => [
                'user_id' => 2,
                'currency' => 'USD',
                'notes' => 'Customer abandoned cart after adding shoes',
                'created_from' => 'mobile',
            ],
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
    }
}
