<?php

declare(strict_types=1);

namespace AIArmada\Orders\Transitions;

use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\ModelStates\Transition;

/**
 * Transition from PendingPayment → Processing.
 *
 * This transition is triggered when payment is confirmed.
 * It records the payment, triggers optional integrations
 * (inventory deduction, affiliate commission), and updates the order state.
 */
final class PaymentConfirmed extends Transition
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
        $originalOrder = $this->order;

        return DB::transaction(function () use ($originalOrder): Order {
            $this->order = $this->order->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->order->getKey());

            $existingPayment = $this->order->payments()
                ->where('gateway', $this->gateway)
                ->where('transaction_id', $this->transactionId)
                ->first();

            if ($existingPayment !== null) {
                if (
                    (int) $existingPayment->amount !== $this->amount
                    || (string) $existingPayment->currency !== (string) $this->order->currency
                ) {
                    throw new InvalidArgumentException(sprintf(
                        'Payment identity %s/%s was already recorded with a different amount or currency.',
                        $this->gateway,
                        $this->transactionId,
                    ));
                }

                $this->syncOriginalOrder($originalOrder);

                return $originalOrder;
            }

            // Record payment
            $this->order->payments()->create([
                'transaction_id' => $this->transactionId,
                'gateway' => $this->gateway,
                'amount' => $this->amount,
                'currency' => $this->order->currency,
                'status' => PaymentStatus::Completed,
                'paid_at' => now(),
                'metadata' => $this->metadata,
            ]);

            // Deduct inventory (if package present)
            if (
                config('orders.integrations.inventory.enabled', true)
                && class_exists(InventoryService::class)
            ) {
                $this->deductInventory();
            }

            // Attribute affiliate commission (if package present)
            if (config('orders.integrations.affiliates.enabled', true)) {
                $this->attributeCommission();
            }

            // Update order state and paid timestamp
            $this->order->status->transitionTo(Processing::class);
            $this->order->paid_at = now();
            $this->order->save();

            // Dispatch events
            event(new OrderPaid($this->order, $this->transactionId, $this->gateway));
            event(new OrderProcessingStarted($this->order, $this->transactionId, $this->gateway));

            $this->syncOriginalOrder($originalOrder);

            return $originalOrder;
        });
    }

    private function syncOriginalOrder(Order $originalOrder): void
    {
        $originalOrder->setRawAttributes($this->order->getAttributes());
        $originalOrder->setRelations($this->order->getRelations());
        $originalOrder->syncOriginal();
    }

    /**
     * Deduct inventory for all order items.
     */
    protected function deductInventory(): void
    {
        event(new InventoryDeductionRequired($this->order));
    }

    /**
     * Attribute affiliate commission.
     */
    protected function attributeCommission(): void
    {
        event(new CommissionAttributionRequired($this->order));
    }
}
