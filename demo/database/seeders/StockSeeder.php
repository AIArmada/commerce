<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class StockSeeder extends Seeder
{
    public function run(): void
    {
        // Skip stock seeding for now due to schema changes
        return;
        $products = Product::with('variants')->get();
        $users = User::all();

        if ($products->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            // Initial stock received
            $this->createTransaction($product, 'in', 'purchase', rand(50, 200), 'Initial stock received');

            // Some sales
            for ($i = 0; $i < rand(3, 10); $i++) {
                $this->createTransaction(
                    $product,
                    'out',
                    'sale',
                    rand(1, 5),
                    'Order fulfillment',
                    now()->subDays(rand(1, 30))
                );
            }

            // Random adjustments
            if (rand(0, 10) > 7) {
                $this->createTransaction(
                    $product,
                    // Deprecated: stock package removed. Seeder retained as placeholder.
            // Random adjustments
