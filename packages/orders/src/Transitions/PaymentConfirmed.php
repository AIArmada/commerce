<?php

declare(strict_types=1);

namespace AIArmada\Orders\Transitions;

use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;
use Spatie\ModelStates\Transition;

/**
 * Transition from PendingPayment → Processing.
 *
 * This transition is triggered when payment is confirmed.
 * It records the payment, triggers optional integrations
 * (inventory deduction, affiliate commission), and updates the order state.
 */
class PaymentConfirmed extends Transition
{
    public function __construct(
        private Order $order,
        private string $transactionId,
        private string $gateway,
        private int $amount,
        /** @var array<string, mixed> */
        private array $metadata = [],
    ) {}

    public function handle(): Order
    {
        // Record payment
        $this->order->payments()->create([
            'transaction_id' => $this->transactionId,
            'gateway' => $this->gateway,
            'amount' => $this->amount,
            'currency' => $this->order->currency,
            'status' => 'completed',
            'paid_at' => now(),
            'metadata' => $this->metadata,
        ]);

        // Deduct inventory (if package present)
        if (
            config('orders.integrations.inventory.enabled', true)
            && class_exists(\AIArmada\Inventory\Services\InventoryService::class)
        ) {
            $this->deductInventory();
        }

        // Attribute affiliate commission (if package present)
        if (
            config('orders.integrations.affiliates.enabled', true)
            && class_exists(\AIArmada\Affiliates\Services\CommissionService::class)
        ) {
            $this->attributeCommission();
        }

        // Update order state and paid timestamp
        $this->order->status->transitionTo(Processing::class);
        $this->order->paid_at = now();
        $this->order->save();

        // Dispatch event
        event(new OrderPaid($this->order, $this->transactionId, $this->gateway));

        return $this->order;
    }

    /**
     * Deduct inventory for all order items.
     */
    protected function deductInventory(): void
    {
        // This would integrate with the inventory package
        // For now, we'll dispatch an event that the inventory package can listen to
        // event(new \AIArmada\Orders\Events\InventoryDeductionRequired($this->order));
    }

    /**
     * Attribute affiliate commission.
     */
    protected function attributeCommission(): void
    {
        // This would integrate with the affiliates package
        // For now, we'll dispatch an event that the affiliates package can listen to
        // event(new \AIArmada\Orders\Events\CommissionAttributionRequired($this->order));
    }
}
