<?php

declare(strict_types=1);

namespace App\Listeners;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Chip\Events\PurchasePaid;
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
}
