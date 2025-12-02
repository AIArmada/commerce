<?php

declare(strict_types=1);

namespace App\Listeners;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

final class HandleChipPaymentSuccess
{
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
        if ($order->payment_status === 'paid') {
            Log::info('Order already marked as paid', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return;
        }

        // Update order status
        $order->update([
            'status' => 'pending',
            'payment_status' => 'paid',
            'placed_at' => now(),
            'metadata' => array_merge($order->metadata ?? [], [
                'chip_purchase_id' => $event->getPurchaseId(),
                'chip_payment_method' => $event->getPaymentMethod(),
                'chip_paid_at' => now()->toISOString(),
            ]),
        ]);

        Log::info('Order updated to paid status', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);

        // Process post-payment actions
        $this->updateStock($order);
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
     * Update stock for order items.
     */
    private function updateStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if ($product && $product->track_stock) {
                $product->removeStock(
                    $item->quantity,
                    'sale',
                    'Order paid: '.$order->order_number
                );
            }
        }

        Log::info('Stock updated for order', [
            'order_id' => $order->id,
            'items_count' => $order->items->count(),
        ]);
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
        if (! $order->voucher_code) {
            return;
        }

        $voucher = Voucher::where('code', $order->voucher_code)->first();

        if (! $voucher) {
            Log::warning('Voucher not found for usage tracking', [
                'order_id' => $order->id,
                'voucher_code' => $order->voucher_code,
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
                'user_id' => $order->user_id,
                'subtotal' => $order->subtotal,
                'grand_total' => $order->grand_total,
            ],
            'redeemed_by_type' => 'order',
            'redeemed_by_id' => $order->id,
            'used_at' => now(),
        ]);

        Log::info('Voucher usage recorded', [
            'order_id' => $order->id,
            'voucher_code' => $order->voucher_code,
            'voucher_id' => $voucher->id,
            'discount_amount' => $order->discount_total,
            'new_applied_count' => $voucher->applied_count,
        ]);
    }
}
