<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntOrderItem;
use AIArmada\Jnt\Models\JntTrackingEvent;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 🚚 J&T EXPRESS SHIPPING SHOWCASE SEEDER
 *
 * Creates a comprehensive shipping demo demonstrating:
 * - Multiple shipment statuses (Pending, In Transit, Delivered, Problem)
 * - Tracking event timelines
 * - Various shipping scenarios (Standard, Express, COD)
 * - Problem shipments for demo
 */
final class JntShippingSeeder extends Seeder
{
    /** @var array<string> */
    private array $trackingStatuses = [
        'PICKUP' => 'Parcel picked up from sender',
        'DEPARTED' => 'Departed from sorting facility',
        'ARRIVED' => 'Arrived at sorting facility',
        'ON_DELIVERY' => 'Out for delivery',
        'DELIVERED' => 'Delivered successfully',
        'RETURNED' => 'Returned to sender',
        'PROBLEM' => 'Delivery issue',
    ];

    /** @var array<array<string, string>> */
    private array $malaysianCities = [
        ['city' => 'Kuala Lumpur', 'state' => 'Kuala Lumpur', 'postcode' => '50000', 'hub' => 'KL Hub'],
        ['city' => 'Petaling Jaya', 'state' => 'Selangor', 'postcode' => '46000', 'hub' => 'PJ Hub'],
        ['city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40000', 'hub' => 'Shah Alam Hub'],
        ['city' => 'Johor Bahru', 'state' => 'Johor', 'postcode' => '80000', 'hub' => 'JB Hub'],
        ['city' => 'Penang', 'state' => 'Penang', 'postcode' => '10000', 'hub' => 'Penang Hub'],
        ['city' => 'Ipoh', 'state' => 'Perak', 'postcode' => '30000', 'hub' => 'Ipoh Hub'],
        ['city' => 'Kuching', 'state' => 'Sarawak', 'postcode' => '93000', 'hub' => 'Kuching Hub'],
        ['city' => 'Kota Kinabalu', 'state' => 'Sabah', 'postcode' => '88000', 'hub' => 'KK Hub'],
        ['city' => 'Melaka', 'state' => 'Melaka', 'postcode' => '75000', 'hub' => 'Melaka Hub'],
        ['city' => 'Seremban', 'state' => 'Negeri Sembilan', 'postcode' => '70000', 'hub' => 'Seremban Hub'],
    ];

    public function run(): void
    {
        $this->command->info('🚚 Creating J&T Express Shipping Demo...');

        $this->createDeliveredShipments();
        $this->createInTransitShipments();
        $this->createPendingShipments();
        $this->createProblemShipments();

        $totalOrders = JntOrder::count();
        $this->command->info("   ✓ Created {$totalOrders} shipping orders with tracking history");
    }

    private function createDeliveredShipments(): void
    {
        // Create 15 delivered shipments with full tracking history
        for ($i = 0; $i < 15; $i++) {
            $order = $this->createShipment(
                status: 'DELIVERED',
                expressType: fake()->randomElement(['EZ', 'EZR']),
                deliveredDaysAgo: rand(1, 30)
            );

            $this->createFullTrackingHistory($order, 'DELIVERED');
        }
    }

    private function createInTransitShipments(): void
    {
        // Create 8 in-transit shipments at various stages
        $stages = ['PICKUP', 'DEPARTED', 'ARRIVED', 'ON_DELIVERY'];

        for ($i = 0; $i < 8; $i++) {
            $currentStage = $stages[array_rand($stages)];

            $order = $this->createShipment(
                status: $currentStage,
                expressType: fake()->randomElement(['EZ', 'NEXT']),
            );

            $this->createPartialTrackingHistory($order, $currentStage);
        }
    }

    private function createPendingShipments(): void
    {
        // Create 5 pending shipments (awaiting pickup)
        for ($i = 0; $i < 5; $i++) {
            $this->createShipment(
                status: 'PENDING',
                expressType: 'EZ',
            );
        }
    }

    private function createProblemShipments(): void
    {
        // Create 3 problem shipments for demo
        $problems = [
            'Recipient not available - 3 delivery attempts failed',
            'Address incomplete - Unable to locate recipient',
            'Parcel damaged during transit - Awaiting instruction',
        ];

        foreach ($problems as $problem) {
            $order = $this->createShipment(
                status: 'PROBLEM',
                expressType: 'EZ',
                hasProblem: true,
                remark: $problem
            );

            $this->createProblemTrackingHistory($order, $problem);
        }
    }

    private function createShipment(
        string $status,
        string $expressType,
        int $deliveredDaysAgo = 0,
        bool $hasProblem = false,
        ?string $remark = null
    ): JntOrder {
        $senderCity = $this->malaysianCities[array_rand($this->malaysianCities)];
        $receiverCity = $this->malaysianCities[array_rand($this->malaysianCities)];

        $orderId = 'ORD-' . Str::upper(Str::random(8));
        $trackingNumber = 'JT' . rand(600000000000, 699999999999);

        $order = JntOrder::create([
            'order_id' => $orderId,
            'tracking_number' => $trackingNumber,
            'customer_code' => config('jnt.customer_code') ?? 'DEMO123',
            'action_type' => '2', // Door-to-door
            'service_type' => '1', // Standard
            'payment_type' => fake()->randomElement(['PP_PM', 'CC']),
            'express_type' => $expressType,
            'status' => $status,
            'sorting_code' => $receiverCity['hub'],
            'package_quantity' => rand(1, 3),
            'package_weight' => (string) (rand(100, 5000) / 100),
            'package_value' => (string) (rand(5000, 100000) / 100),
            'goods_type' => 'PACKAGE',
            'ordered_at' => now()->subDays($deliveredDaysAgo + rand(1, 5)),
            'last_synced_at' => now()->subHours(rand(1, 24)),
            'last_tracked_at' => now()->subMinutes(rand(5, 120)),
            'delivered_at' => $status === 'DELIVERED' ? now()->subDays($deliveredDaysAgo) : null,
            'last_status_code' => $this->getStatusCode($status),
            'last_status' => $this->trackingStatuses[$status] ?? $status,
            'has_problem' => $hasProblem,
            'remark' => $remark,
            'sender' => [
                'name' => 'AIArmada Commerce',
                'phone' => '+60123456789',
                'address' => 'Lot 15, Jalan Perusahaan 2, Shah Alam Industrial Park',
                'city' => $senderCity['city'],
                'state' => $senderCity['state'],
                'postcode' => $senderCity['postcode'],
                'country' => 'MY',
            ],
            'receiver' => [
                'name' => fake()->name(),
                'phone' => '+60' . rand(100000000, 199999999),
                'address' => fake()->streetAddress() . ', ' . fake()->buildingNumber(),
                'city' => $receiverCity['city'],
                'state' => $receiverCity['state'],
                'postcode' => $receiverCity['postcode'],
                'country' => 'MY',
            ],
            'metadata' => [
                'source' => 'demo_seeder',
                'commerce_order_id' => Str::uuid()->toString(),
            ],
        ]);

        // Create order items
        $itemCount = rand(1, 3);
        for ($i = 0; $i < $itemCount; $i++) {
            JntOrderItem::create([
                'order_id' => $order->id,
                'name' => fake()->randomElement([
                    'iPhone 15 Pro',
                    'Samsung Galaxy S24',
                    'Sony WH-1000XM5',
                    'MacBook Pro 14"',
                    'AirPods Pro',
                    'Smart Watch',
                    'Wireless Charger',
                    'Phone Case',
                ]),
                'quantity' => rand(1, 2),
                'weight_grams' => rand(100, 2000),
                'unit_price' => rand(5000, 500000),
                'currency' => 'MYR',
            ]);
        }

        return $order;
    }

    private function createFullTrackingHistory(JntOrder $order, string $finalStatus): void
    {
        $stages = ['PICKUP', 'DEPARTED', 'ARRIVED', 'ON_DELIVERY', 'DELIVERED'];
        $time = $order->ordered_at ?? now()->subDays(5);

        foreach ($stages as $index => $stage) {
            $time = $time->copy()->addHours(rand(4, 24));

            if ($stage === 'DELIVERED' && $order->delivered_at) {
                $time = $order->delivered_at;
            }

            JntTrackingEvent::create([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'scan_type_code' => $this->getStatusCode($stage),
                'scan_type_name' => $stage,
                'description' => $this->trackingStatuses[$stage],
                'scan_network_city' => $this->getLocationForStage($stage, $order),
                'scan_time' => $time,
                'payload' => [
                    'billCode' => $order->tracking_number,
                    'scanType' => $stage,
                ],
            ]);

            if ($stage === $finalStatus) {
                break;
            }
        }
    }

    private function createPartialTrackingHistory(JntOrder $order, string $currentStage): void
    {
        $allStages = ['PICKUP', 'DEPARTED', 'ARRIVED', 'ON_DELIVERY'];
        $time = $order->ordered_at ?? now()->subDays(2);

        foreach ($allStages as $stage) {
            $time = $time->copy()->addHours(rand(4, 24));

            JntTrackingEvent::create([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'scan_type_code' => $this->getStatusCode($stage),
                'scan_type_name' => $stage,
                'description' => $this->trackingStatuses[$stage],
                'scan_network_city' => $this->getLocationForStage($stage, $order),
                'scan_time' => $time,
                'payload' => [
                    'billCode' => $order->tracking_number,
                    'scanType' => $stage,
                ],
            ]);

            if ($stage === $currentStage) {
                break;
            }
        }
    }

    private function createProblemTrackingHistory(JntOrder $order, string $problem): void
    {
        $time = $order->ordered_at ?? now()->subDays(3);

        // Normal flow until ON_DELIVERY
        $stages = ['PICKUP', 'DEPARTED', 'ARRIVED', 'ON_DELIVERY'];

        foreach ($stages as $stage) {
            $time = $time->copy()->addHours(rand(4, 12));

            JntTrackingEvent::create([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'scan_type_code' => $this->getStatusCode($stage),
                'scan_type_name' => $stage,
                'description' => $this->trackingStatuses[$stage],
                'scan_network_city' => $this->getLocationForStage($stage, $order),
                'scan_time' => $time,
                'payload' => [
                    'billCode' => $order->tracking_number,
                    'scanType' => $stage,
                ],
            ]);
        }

        // Add problem event
        $time = $time->copy()->addHours(rand(2, 6));

        JntTrackingEvent::create([
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'scan_type_code' => 'ERR',
            'scan_type_name' => 'PROBLEM',
            'description' => $problem,
            'scan_network_city' => $order->receiver['city'] ?? 'Unknown',
            'problem_type' => 'delivery_failed',
            'remark' => $problem,
            'scan_time' => $time,
            'payload' => [
                'billCode' => $order->tracking_number,
                'scanType' => 'PROBLEM',
                'remark' => $problem,
            ],
        ]);
    }

    private function getStatusCode(string $status): string
    {
        return match ($status) {
            'PENDING' => 'PND',
            'PICKUP' => 'PU',
            'DEPARTED' => 'DEP',
            'ARRIVED' => 'ARR',
            'ON_DELIVERY' => 'OFD',
            'DELIVERED' => 'DLV',
            'RETURNED' => 'RTN',
            'PROBLEM' => 'ERR',
            default => 'UNK',
        };
    }

    private function getLocationForStage(string $stage, JntOrder $order): string
    {
        $sender = $order->sender ?? [];
        $receiver = $order->receiver ?? [];

        return match ($stage) {
            'PICKUP' => ($sender['city'] ?? 'Origin') . ' Hub',
            'DEPARTED' => ($sender['city'] ?? 'Origin') . ' Sorting Center',
            'ARRIVED' => ($receiver['city'] ?? 'Destination') . ' Hub',
            'ON_DELIVERY' => ($receiver['city'] ?? 'Destination') . ' - Delivery Vehicle',
            'DELIVERED' => $receiver['address'] ?? 'Recipient Address',
            default => 'J&T Express Hub',
        };
    }
}
