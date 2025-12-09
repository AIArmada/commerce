<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

final class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $products = Product::all();

        if ($users->isEmpty() || $products->isEmpty()) {
            return;
        }

        $orderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

        // Create 20 sample orders
        for ($i = 0; $i < 20; $i++) {
            $user = $users->random();
            $status = $orderStatuses[array_rand($orderStatuses)];
            $itemCount = rand(1, 4);
            $orderProducts = $products->random($itemCount);

            $subtotal = 0;
            $items = [];

            foreach ($orderProducts as $product) {
                $quantity = rand(1, 3);
                $unitPrice = $product->price;
                $totalPrice = $quantity * $unitPrice;
                $subtotal += $totalPrice;

                $items[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }

            $discountTotal = rand(0, 1) ? rand(500, 5000) : 0;
            $taxTotal = (int) round($subtotal * 0.06); // 6% SST
            $shippingTotal = rand(0, 1) ? rand(500, 2000) : 0;
            $grandTotal = $subtotal - $discountTotal + $taxTotal + $shippingTotal;

            $paymentStatus = match ($status) {
                'pending' => 'pending',
                'cancelled' => 'refunded',
                default => 'paid',
            };

            $billingAddress = [
                'name' => $user->name,
                'phone' => fake()->phoneNumber(),
                'address_line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->randomElement(['Selangor', 'Kuala Lumpur', 'Penang', 'Johor', 'Sabah']),
                'postcode' => fake()->postcode(),
                'country' => 'MY',
            ];

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . mb_strtoupper(fake()->unique()->bothify('???####')),
                'status' => $status,
                'payment_status' => $paymentStatus,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'currency' => 'MYR',
                'billing_address' => $billingAddress,
                'shipping_address' => $billingAddress,
                'placed_at' => fake()->dateTimeBetween('-30 days', 'now'),
                'paid_at' => $paymentStatus === 'paid' ? fake()->dateTimeBetween('-29 days', 'now') : null,
                'shipped_at' => in_array($status, ['shipped', 'delivered']) ? fake()->dateTimeBetween('-20 days', 'now') : null,
                'delivered_at' => $status === 'delivered' ? fake()->dateTimeBetween('-10 days', 'now') : null,
                'cancelled_at' => $status === 'cancelled' ? fake()->dateTimeBetween('-5 days', 'now') : null,
            ]);

            foreach ($items as $itemData) {
                OrderItem::create([
                    ...$itemData,
                    'order_id' => $order->id,
                ]);
            }
        }
    }
}
