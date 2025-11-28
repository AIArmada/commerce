<?php

namespace AIArmada\CashierChip\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use AIArmada\CashierChip\CashierChip;
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Events\WebhookHandled;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    /**
     * Create a new webhook controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (config('cashier-chip.webhooks.verify_signature', true)) {
            $this->middleware(VerifyWebhookSignature::class);
        }
    }

    /**
     * Handle a CHIP webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->all();

        WebhookReceived::dispatch($payload);

        $eventType = $payload['event_type'] ?? '';
        $method = 'handle'.Str::studly(str_replace('.', '_', $eventType));

        if (method_exists($this, $method)) {
            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod($payload);
    }

    /**
     * Handle purchase.paid event.
     * CHIP sends this when a purchase payment is completed successfully.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchasePaid(array $payload): Response
    {
        return $this->handlePaymentSuccess($payload);
    }

    /**
     * Handle purchase.payment_failure event.
     * CHIP sends this when a purchase payment fails.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchasePaymentFailure(array $payload): Response
    {
        return $this->handlePaymentFailed($payload);
    }

    /**
     * Handle purchase.created event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchaseCreated(array $payload): Response
    {
        // Purchase created - no action needed unless tracking
        return $this->successMethod();
    }

    /**
     * Handle purchase.cancelled event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchaseCancelled(array $payload): Response
    {
        // Purchase cancelled - may need to update subscription status
        $purchase = $payload['purchase'] ?? $payload;
        $clientId = $purchase['client']['id'] ?? $purchase['client_id'] ?? null;

        if ($clientId && $billable = CashierChip::findBillable($clientId)) {
            // Check if this was a subscription payment
            if ($subscriptionType = $this->getSubscriptionTypeFromPurchase($purchase)) {
                $subscription = $billable->subscription($subscriptionType);
                if ($subscription) {
                    $subscription->forceFill(['chip_status' => 'past_due'])->save();
                }
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle purchase.refunded event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchaseRefunded(array $payload): Response
    {
        // Handle refund - may need custom logic
        return $this->successMethod();
    }

    /**
     * Handle purchase.hold event.
     * CHIP sends this when payment is placed on hold (skip_capture = true).
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchaseHold(array $payload): Response
    {
        // Payment is on hold, waiting for capture
        return $this->successMethod();
    }

    /**
     * Handle purchase.preauthorized event.
     * CHIP sends this when card is preauthorized (card saved without charge).
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchasePreauthorized(array $payload): Response
    {
        $purchase = $payload['purchase'] ?? $payload;
        $clientId = $purchase['client']['id'] ?? $purchase['client_id'] ?? null;

        // Save the recurring token for future use
        if ($clientId && $billable = CashierChip::findBillable($clientId)) {
            if ($recurringToken = $purchase['recurring_token'] ?? null) {
                $this->handleRecurringToken($billable, $recurringToken, $purchase);
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle purchase.chargeback event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePurchaseChargeback(array $payload): Response
    {
        // Handle chargeback - may need to cancel subscription
        $purchase = $payload['purchase'] ?? $payload;
        $clientId = $purchase['client']['id'] ?? $purchase['client_id'] ?? null;

        if ($clientId && $billable = CashierChip::findBillable($clientId)) {
            if ($subscriptionType = $this->getSubscriptionTypeFromPurchase($purchase)) {
                $subscription = $billable->subscription($subscriptionType);
                if ($subscription) {
                    $subscription->forceFill(['chip_status' => 'unpaid'])->save();
                }
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle payment.refunded event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePaymentRefunded(array $payload): Response
    {
        return $this->successMethod();
    }

    /**
     * Handle a purchase payment succeeded event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePaymentSuccess(array $payload): Response
    {
        $purchase = $payload['purchase'] ?? $payload;
        $clientId = $purchase['client']['id'] ?? $purchase['client_id'] ?? null;

        if ($clientId && $billable = CashierChip::findBillable($clientId)) {
            PaymentSucceeded::dispatch($billable, $purchase);

            // Handle recurring token if present
            if ($recurringToken = $purchase['recurring_token'] ?? null) {
                $this->handleRecurringToken($billable, $recurringToken, $purchase);
            }

            // Handle subscription charge if this is a subscription payment
            if ($subscriptionType = $this->getSubscriptionTypeFromPurchase($purchase)) {
                $this->handleSubscriptionPayment($billable, $subscriptionType, $purchase);
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle a purchase payment failed event.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePaymentFailed(array $payload): Response
    {
        $purchase = $payload['purchase'] ?? $payload;
        $clientId = $purchase['client']['id'] ?? $purchase['client_id'] ?? null;

        if ($clientId && $billable = CashierChip::findBillable($clientId)) {
            PaymentFailed::dispatch($billable, $purchase);

            // Handle subscription payment failure
            if ($subscriptionType = $this->getSubscriptionTypeFromPurchase($purchase)) {
                $this->handleSubscriptionPaymentFailure($billable, $subscriptionType, $purchase);
            }
        }

        return $this->successMethod();
    }

    /**
     * Extract subscription type from purchase metadata or reference.
     *
     * @param  array  $purchase
     * @return string|null
     */
    protected function getSubscriptionTypeFromPurchase(array $purchase): ?string
    {
        // Check metadata first (could be nested or at top level)
        $metadata = $purchase['metadata'] ?? $purchase['purchase']['metadata'] ?? [];
        if (isset($metadata['subscription_type'])) {
            return $metadata['subscription_type'];
        }

        // Check reference for subscription info
        $reference = $purchase['reference'] ?? '';
        if (preg_match('/Subscription (\w+)/', $reference, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Handle recurring token from a purchase.
     *
     * @param  \AIArmada\CashierChip\Billable  $billable
     * @param  string  $recurringToken
     * @param  array  $purchase
     * @return void
     */
    protected function handleRecurringToken($billable, string $recurringToken, array $purchase): void
    {
        // Store the recurring token if not already stored
        if (! $billable->hasDefaultPaymentMethod()) {
            // Get card details from transaction_data if available
            $transactionData = $purchase['transaction_data'] ?? [];
            $extra = $transactionData['extra'] ?? [];
            
            // Also check for card info at top level (test format)
            $card = $purchase['card'] ?? [];
            
            $billable->forceFill([
                'default_pm_id' => $recurringToken,
                'pm_type' => $card['brand'] ?? $extra['card_brand'] ?? $transactionData['payment_method'] ?? 'card',
                'pm_last_four' => $card['last_4'] ?? $extra['card_last_4'] ?? null,
            ])->save();
        }
    }

    /**
     * Handle a subscription payment success.
     *
     * @param  \AIArmada\CashierChip\Billable  $billable
     * @param  string  $subscriptionType
     * @param  array  $purchase
     * @return void
     */
    protected function handleSubscriptionPayment($billable, string $subscriptionType, array $purchase): void
    {
        $subscription = $billable->subscription($subscriptionType);

        if ($subscription) {
            // Update next billing date
            $interval = $subscription->billing_interval ?? 'month';
            $count = $subscription->billing_interval_count ?? 1;

            $subscription->forceFill([
                'chip_status' => 'active',
                'next_billing_at' => now()->add($interval, $count),
            ])->save();
        }
    }

    /**
     * Handle a subscription payment failure.
     *
     * @param  \AIArmada\CashierChip\Billable  $billable
     * @param  string  $subscriptionType
     * @param  array  $purchase
     * @return void
     */
    protected function handleSubscriptionPaymentFailure($billable, string $subscriptionType, array $purchase): void
    {
        $subscription = $billable->subscription($subscriptionType);

        if ($subscription) {
            $subscription->forceFill([
                'chip_status' => 'past_due',
            ])->save();
        }
    }

    /**
     * Handle successful calls on the controller.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod(): Response
    {
        return new Response('Webhook handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function missingMethod(array $payload): Response
    {
        return new Response('Webhook received', 200);
    }
}
