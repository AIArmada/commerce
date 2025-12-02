<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Stock\Models\StockTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use DateTimeInterface;
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
                    rand(0, 1) ? 'in' : 'out',
                    'adjustment',
                    rand(1, 10),
                    'Inventory audit adjustment'
                );
            }

            // Restock for some products
            if ($product->isLowStock() || rand(0, 10) > 5) {
                $this->createTransaction(
                    $product,
                    'in',
                    'restock',
                    rand(20, 100),
                    'Restock from supplier'
                );
            }

            // Create transactions for variants
            foreach ($product->variants as $variant) {
                // Initial stock
                $this->createVariantTransaction($variant, 'in', 'purchase', rand(20, 50), 'Initial variant stock');

                // Sales
                for ($i = 0; $i < rand(1, 5); $i++) {
                    $this->createVariantTransaction(
                        $variant,
                        'out',
                        'sale',
                        rand(1, 3),
                        'Variant sold',
                        now()->subDays(rand(1, 20))
                    );
                }
            }
        }

        // Create some returns
        $returnedProducts = $products->random(min(5, $products->count()));
        foreach ($returnedProducts as $product) {
            $this->createTransaction(
                $product,
                'in',
                'return',
                rand(1, 3),
                'Customer return - order refunded'
            );
        }

        // Create some damaged/expired stock removals
        $damagedProducts = $products->random(min(3, $products->count()));
        foreach ($damagedProducts as $product) {
            $this->createTransaction(
                $product,
                'out',
                'damaged',
                rand(1, 5),
                'Damaged during storage'
            );
        }

        // Reserved stock for pending orders
        $reservedProducts = $products->random(min(4, $products->count()));
        foreach ($reservedProducts as $product) {
            $this->createTransaction(
                $product,
                'out',
                'reserved',
                rand(1, 3),
                'Reserved for pending order'
            );
        }
    }

    private function createTransaction(
        Product $product,
        string $type,
        string $reason,
        int $quantity,
        ?string $note = null,
        ?DateTimeInterface $date = null
    ): StockTransaction {
        return StockTransaction::create([
            'stockable_type' => Product::class,
            'stockable_id' => $product->id,
            'user_id' => User::inRandomOrder()->first()?->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'note' => $note,
            'transaction_date' => $date ?? now(),
        ]);
    }

    private function createVariantTransaction(
        ProductVariant $variant,
        string $type,
        string $reason,
        int $quantity,
        ?string $note = null,
        ?DateTimeInterface $date = null
    ): StockTransaction {
        return StockTransaction::create([
            'stockable_type' => ProductVariant::class,
            'stockable_id' => $variant->id,
            'user_id' => User::inRandomOrder()->first()?->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'note' => $note,
            'transaction_date' => $date ?? now(),
        ]);
    }
}
