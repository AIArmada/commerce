# Payment Integration

> **Document:** 04 of 08  
> **Package:** `aiarmada/orders`  
> **Status:** Vision

---

## Overview

Orders integrate with the Cashier package for payment processing but maintain their own payment records for audit purposes. This ensures orders can exist independently of any payment gateway.

---

## Payment Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PAYMENT FLOW                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│    Customer                    Orders Package              Cashier Package   │
│        │                            │                            │          │
│        │  1. Checkout               │                            │          │
│        │────────────────────────────▶                            │          │
│        │                            │                            │          │
│        │                            │  2. Create Order           │          │
│        │                            │  (PendingPayment state)    │          │
│        │                            │                            │          │
│        │                            │  3. Initiate Payment       │          │
│        │                            │───────────────────────────▶│          │
│        │                            │                            │          │
│        │                            │  4. Payment Intent         │          │
│        │◀───────────────────────────────────────────────────────│          │
│        │                            │                            │          │
│        │  5. Complete Payment       │                            │          │
│        │───────────────────────────────────────────────────────▶│          │
│        │                            │                            │          │
│        │                            │  6. Webhook Callback       │          │
│        │                            │◀───────────────────────────│          │
│        │                            │                            │          │
│        │                            │  7. Record Payment         │          │
│        │                            │  8. Transition to          │          │
│        │                            │     Processing state       │          │
│        │                            │                            │          │
│        │  9. Confirmation           │                            │          │
│        │◀───────────────────────────│                            │          │
│        │                            │                            │          │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Payment Recording

```php
namespace AIArmada\Orders\Services;

class PaymentRecorder
{
    public function recordPayment(
        Order $order,
        string $gateway,
        string $transactionId,
        int $amount,
        string $status = 'completed',
        array $metadata = []
    ): OrderPayment {
        $payment = $order->payments()->create([
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $order->currency,
            'status' => $status,
            'metadata' => $metadata,
            'paid_at' => $status === 'completed' ? now() : null,
        ]);

        if ($status === 'completed' && $this->isOrderFullyPaid($order)) {
            $order->status->transitionTo(Processing::class);
        }

        return $payment;
    }

    protected function isOrderFullyPaid(Order $order): bool
    {
        $totalPaid = $order->payments()
            ->where('status', 'completed')
            ->sum('amount');

        return $totalPaid >= $order->grand_total;
    }
}
```

---

## Supported Payment Methods

| Gateway | Package | Supported Methods |
|---------|---------|-------------------|
| Stripe | `aiarmada/cashier` | Card, Apple Pay, Google Pay |
| CHIP | `aiarmada/cashier-chip` | FPX, Card, Razer eWallet |
| Manual | Built-in | Bank Transfer, Cash, COD |

---

## Refund Processing

```php
namespace AIArmada\Orders\Services;

class RefundProcessor
{
    public function refund(
        Order $order,
        int $amount,
        string $reason,
        ?OrderPayment $payment = null
    ): OrderRefund {
        // Use specific payment or last successful payment
        $payment ??= $order->payments()
            ->where('status', 'completed')
            ->latest()
            ->firstOrFail();

        // Process via gateway
        $gatewayRefund = $this->processGatewayRefund($payment, $amount);

        // Record refund
        $refund = $order->refunds()->create([
            'payment_id' => $payment->id,
            'amount' => $amount,
            'reason' => $reason,
            'gateway_refund_id' => $gatewayRefund->id ?? null,
            'status' => 'completed',
            'refunded_by' => auth()->id(),
            'refunded_at' => now(),
        ]);

        // Restock if full refund and inventory package present
        if ($this->isFullRefund($order) && class_exists(InventoryService::class)) {
            app(InventoryService::class)->restoreForOrder($order);
        }

        // Record history
        $order->history()->create([
            'event' => OrderEvent::Refunded,
            'description' => "Refunded {$amount} - {$reason}",
        ]);

        return $refund;
    }

    protected function processGatewayRefund(OrderPayment $payment, int $amount): ?object
    {
        return match ($payment->gateway) {
            'stripe' => $this->refundStripe($payment, $amount),
            'chip' => $this->refundChip($payment, $amount),
            'manual' => null, // No gateway to call
        };
    }
}
```

---

## Navigation

**Previous:** [03-order-structure.md](03-order-structure.md)  
**Next:** [05-fulfillment-flow.md](05-fulfillment-flow.md)
