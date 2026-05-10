<?php

declare(strict_types=1);

namespace App\Listeners;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Support\Facades\Log;

final class HandleChipPaymentSuccess
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Handle the PurchasePaid event from CHIP webhook.
     */
    public function handle(PurchasePaid $event): void
    {
        Log::info('CHIP PurchasePaid webhook received', [
            'purchase_id' => $event->getPurchaseId(),
            'reference' => $event->getReference(),
            'amount' => $event->getAmount(),
            'metadata' => $event->getMetadata(),
        ]);

        // Find the order by reference (order_number) or metadata
        $order = $this->findOrder($event);

        if (! $order) {
            Log::warning('Order not found for CHIP payment', [
                'purchase_id' => $event->getPurchaseId(),
                'reference' => $event->getReference(),
            ]);

            return;
        }

        // Don't process if already paid
        if ($order->isPaid() || $order->payments()->where('transaction_id', $event->getPurchaseId())->exists()) {
            $this->ensureShipmentCreated($order);

            Log::info('Order already marked as paid', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        $this->orderService->confirmPayment(
            order: $order,
            transactionId: $event->getPurchaseId(),
            gateway: 'chip',
            amount: $event->getAmount(),
            metadata: [
                'chip_purchase_id' => $event->getPurchaseId(),
                'chip_payment_method' => $event->getPaymentMethod(),
                'chip_reference' => $event->getReference(),
            ],
        );

        $order->metadata = array_merge($order->metadata ?? [], [
            'chip_purchase_id' => $event->getPurchaseId(),
            'chip_payment_method' => $event->getPaymentMethod(),
            'chip_paid_at' => now()->toISOString(),
        ]);
        $order->save();

        Log::info('Order updated to paid status', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);

        $this->ensureShipmentCreated($order);

        // Process post-payment actions
        $this->trackAffiliateConversion($order, $event);
        $this->updateVoucherUsage($order);
    }

    /**
     * Find order by reference or metadata.
     */
    private function findOrder(PurchasePaid $event): ?Order
    {
        // First try by reference (order_number)
        if ($event->getReference()) {
            $order = Order::where('order_number', $event->getReference())->first();
            if ($order) {
                return $order;
            }
        }

        // Then try by CHIP purchase ID in metadata
        $metadata = $event->getMetadata();
        if ($metadata && isset($metadata['order_id'])) {
            return Order::find($metadata['order_id']);
        }

        // Finally try finding by chip_purchase_id in order metadata
        return Order::whereJsonContains('metadata->chip_purchase_id', $event->getPurchaseId())->first();
    }

    /**
     * Track affiliate conversion if applicable.
     */
    private function trackAffiliateConversion(Order $order, PurchasePaid $event): void
    {
        $affiliateCode = $order->metadata['affiliate_code'] ?? $event->getMetadataValue('affiliate_code');

        if (! $affiliateCode) {
            return;
        }

        $affiliate = Affiliate::where('code', $affiliateCode)->first();

        if (! $affiliate) {
            Log::warning('Affiliate not found for conversion', [
                'order_id' => $order->id,
                'affiliate_code' => $affiliateCode,
            ]);

            return;
        }

        $affiliate->conversions()->create([
            'order_reference' => $order->order_number,
            'subtotal_minor' => $order->subtotal,
            'total_minor' => $order->grand_total,
            'commission_minor' => $affiliate->calculateCommission($order->subtotal),
            'commission_currency' => 'MYR',
            'status' => 'pending',
        ]);

        Log::info('Affiliate conversion tracked', [
            'order_id' => $order->id,
            'affiliate_code' => $affiliateCode,
            'commission' => $affiliate->calculateCommission($order->subtotal),
        ]);
    }

    /**
     * Update voucher usage if applicable.
     */
    private function updateVoucherUsage(Order $order): void
    {
        $voucherCode = $order->metadata['voucher_code'] ?? null;

        if (! is_string($voucherCode) || $voucherCode === '') {
            return;
        }

        $voucher = Voucher::where('code', $voucherCode)->first();

        if (! $voucher) {
            Log::warning('Voucher not found for usage tracking', [
                'order_id' => $order->id,
                'voucher_code' => $voucherCode,
            ]);

            return;
        }

        // Create a VoucherUsage record for redemption tracking
        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => $order->discount_total,
            'currency' => $order->currency ?? 'MYR',
            'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
            'notes' => null,
            'metadata' => [
                'customer_id' => $order->customer_id,
                'customer_type' => $order->customer_type,
                'subtotal' => $order->subtotal,
                'grand_total' => $order->grand_total,
            ],
            'redeemed_by_type' => 'order',
            'redeemed_by_id' => $order->id,
            'used_at' => now(),
        ]);

        Log::info('Voucher usage recorded', [
            'order_id' => $order->id,
            'voucher_code' => $voucherCode,
            'voucher_id' => $voucher->id,
            'discount_amount' => $order->discount_total,
            'new_applied_count' => $voucher->applied_count,
        ]);
    }

    private function ensureShipmentCreated(Order $order): void
    {
        $owner = OwnerContext::fromTypeAndId($order->owner_type, $order->owner_id);

        OwnerContext::withOwner($owner, function () use ($order): void {
            $order->loadMissing('items', 'shippingAddress');

            $shipment = JntOrder::query()
                ->where('order_id', $order->order_number)
                ->first();

            if ($shipment === null) {
                $shippingAddress = $order->shippingAddress;

                if ($shippingAddress === null) {
                    Log::warning('Skipping demo shipment creation because shipping address is missing.', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);

                    return;
                }

                $shippingMethod = (string) ($order->metadata['shipping_method'] ?? 'jnt_standard');
                $packageWeightGrams = max(
                    100,
                    (int) $order->items->sum(fn ($item): int => (int) (($item->metadata['weight'] ?? 100) * $item->quantity)),
                );

                $shipment = JntOrder::create([
                    'order_id' => $order->order_number,
                    'tracking_number' => $this->generateTrackingNumber(),
                    'customer_code' => (string) (config('jnt.customer_code') ?: 'DEMO123'),
                    'action_type' => '2',
                    'service_type' => '1',
                    'payment_type' => 'PP_PM',
                    'express_type' => $shippingMethod === 'jnt_express' ? 'NEXT' : 'EZ',
                    'status' => 'PICKUP',
                    'sorting_code' => (string) ($shippingAddress->city ?? 'Demo Hub'),
                    'package_quantity' => max(1, (int) $order->items->sum('quantity')),
                    'package_weight' => number_format($packageWeightGrams / 1000, 2, '.', ''),
                    'package_value' => number_format($order->grand_total / 100, 2, '.', ''),
                    'goods_type' => 'PACKAGE',
                    'ordered_at' => now(),
                    'last_synced_at' => now(),
                    'last_tracked_at' => now(),
                    'last_status_code' => 'PU',
                    'last_status' => 'Parcel picked up from sender',
                    'remark' => 'Demo shipment created automatically after payment confirmation.',
                    'sender' => [
                        'name' => config('app.name', 'Commerce Demo'),
                        'phone' => '+60123456789',
                        'address' => 'Demo Fulfillment Centre',
                        'city' => 'Shah Alam',
                        'state' => 'Selangor',
                        'postcode' => '40000',
                        'country' => 'MY',
                    ],
                    'receiver' => [
                        'name' => $shippingAddress->getFullName(),
                        'phone' => $shippingAddress->phone,
                        'address' => trim(implode(', ', array_filter([
                            $shippingAddress->line1,
                            $shippingAddress->line2,
                        ]))),
                        'city' => $shippingAddress->city,
                        'state' => $shippingAddress->state,
                        'postcode' => $shippingAddress->postcode,
                        'country' => $shippingAddress->country ?? 'MY',
                    ],
                    'metadata' => [
                        'source' => 'demo_checkout',
                        'commerce_order_id' => $order->id,
                        'shipping_method' => $shippingMethod,
                    ],
                    'owner_type' => $order->owner_type,
                    'owner_id' => $order->owner_id,
                ]);

                Log::info('Demo J&T shipment created for paid order.', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'tracking_number' => $shipment->tracking_number,
                ]);
            }

            if (! $shipment->trackingEvents()->exists()) {
                $this->createInitialTrackingEvent($shipment);
            }
        });
    }

    private function createInitialTrackingEvent(JntOrder $shipment): void
    {
        JntTrackingEvent::create([
            'order_id' => $shipment->id,
            'tracking_number' => (string) $shipment->tracking_number,
            'order_reference' => $shipment->order_id,
            'scan_type_code' => 'PU',
            'scan_type_name' => 'PICKUP',
            'description' => 'Parcel picked up from sender',
            'scan_network_city' => $shipment->sender['city'] ?? 'Shah Alam',
            'scan_network_province' => $shipment->sender['state'] ?? 'Selangor',
            'scan_network_country' => $shipment->sender['country'] ?? 'MY',
            'scan_time' => now(),
            'payload' => [
                'billCode' => $shipment->tracking_number,
                'scanType' => 'PICKUP',
                'orderId' => $shipment->order_id,
            ],
            'owner_type' => $shipment->owner_type,
            'owner_id' => $shipment->owner_id,
        ]);
    }

    private function generateTrackingNumber(): string
    {
        return 'JT' . (string) random_int(600000000000, 699999999999);
    }
}
