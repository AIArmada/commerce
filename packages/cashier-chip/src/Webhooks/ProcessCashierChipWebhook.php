<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Webhooks;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\WebhookHandled;
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Subscription;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ProcessCashierChipWebhook extends CommerceWebhookProcessor
{
    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $ownerType = Arr::get($payload, '__owner_type');
        $ownerId = Arr::get($payload, '__owner_id');

        $executor = function () use ($eventType, $payload): void {
            WebhookReceived::dispatch($payload);

            match ($eventType) {
                'purchase.paid' => $this->handlePurchasePaid($payload),
                'purchase.payment_failure' => $this->handlePurchasePaymentFailure($payload),
                default => null,
            };

            WebhookHandled::dispatch($payload);
        };

        if (is_string($ownerType) && (is_string($ownerId) || is_int($ownerId))) {
            $owner = OwnerContext::fromTypeAndId($ownerType, $ownerId);

            if ($owner instanceof Model) {
                OwnerContext::withOwner($owner, $executor);

                return;
            }
        }

        $executor();
    }

    /**
     * Handle purchase.paid webhook event.
     */
    protected function handlePurchasePaid(array $payload): void
    {
        $purchase = $payload['purchase'] ?? [];
        $clientId = $purchase['client']['id'] ?? null;

        if (!$clientId) {
            return;
        }

        $billable = $this->getBillableByChipId($clientId);

        if (!$billable) {
            return;
        }

        // Update default payment method if recurring token provided
        $this->updatePaymentMethodFromWebhook($billable, $purchase);

        // Update subscription status if applicable
        $this->updateSubscriptionOnPaymentSuccess($billable, $purchase);

        // Dispatch backward-compatible event
        PaymentSucceeded::dispatch($billable, $purchase);
    }

    /**
     * Handle purchase.payment_failure webhook event.
     */
    protected function handlePurchasePaymentFailure(array $payload): void
    {
        $purchase = $payload['purchase'] ?? [];
        $clientId = $purchase['client']['id'] ?? null;

        if (!$clientId) {
            return;
        }

        $billable = $this->getBillableByChipId($clientId);

        if (!$billable) {
            return;
        }

        // Update subscription status to past due
        $this->updateSubscriptionOnPaymentFailure($billable, $purchase);

        // Dispatch backward-compatible event
        PaymentFailed::dispatch($billable, $purchase);
    }

    /**
     * Update payment method from webhook data.
     *
     * @param  array<string, mixed>  $purchase
     * @phpstan-param Model&BillableContract $billable
     */
    protected function updatePaymentMethodFromWebhook(Model $billable, array $purchase): void
    {
        $recurringToken = $purchase['recurring_token'] ?? null;

        if (!$recurringToken) {
            return;
        }

        // Only update if no default payment method exists
        if ($billable->getAttribute('default_pm_id')) {
            return;
        }

        $card = $purchase['card'] ?? [];

        $billable->forceFill([
            'default_pm_id' => $recurringToken,
            'pm_type' => $card['brand'] ?? null,
            'pm_last_four' => $card['last_4'] ?? null,
        ])->save();
    }

    /**
     * Update subscription status on payment success.
     *
     * @param  array<string, mixed>  $purchase
     * @phpstan-param Model&BillableContract $billable
     */
    protected function updateSubscriptionOnPaymentSuccess(Model $billable, array $purchase): void
    {
        $subscriptionType = $purchase['metadata']['subscription_type'] ?? null;

        if (!$subscriptionType) {
            return;
        }

        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if (!$subscription) {
            return;
        }

        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'next_billing_at' => $this->calculateNextBillingDate($subscription),
        ])->save();
    }

    /**
     * Update subscription status on payment failure.
     *
     * @param  array<string, mixed>  $purchase
     * @phpstan-param Model&BillableContract $billable
     */
    protected function updateSubscriptionOnPaymentFailure(Model $billable, array $purchase): void
    {
        $subscriptionType = $purchase['metadata']['subscription_type'] ?? null;

        if (!$subscriptionType) {
            return;
        }

        $subscription = Cashier::findSubscriptionForWebhook($billable, $subscriptionType);

        if (!$subscription) {
            return;
        }

        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ])->save();
    }

    /**
     * Calculate the next billing date based on subscription interval.
     */
    protected function calculateNextBillingDate(Subscription $subscription): Carbon
    {
        $interval = $subscription->billing_interval ?? 'month';
        $intervalCount = $subscription->billing_interval_count ?? 1;

        return match ($interval) {
            'day' => Carbon::now()->addDays($intervalCount),
            'week' => Carbon::now()->addWeeks($intervalCount),
            'month' => Carbon::now()->addMonths($intervalCount),
            'year' => Carbon::now()->addYears($intervalCount),
            default => Carbon::now()->addMonth(),
        };
    }

    /**
     * Get the billable instance by CHIP ID.
     *
     * @phpstan-return (Model&BillableContract)|null
     */
    protected function getBillableByChipId(?string $chipId): ?Model
    {
        if (!$chipId) {
            return null;
        }

        if ((bool) config('cashier-chip.features.owner.enabled', true)) {
            return Cashier::findBillableForWebhook($chipId);
        }

        return Cashier::findBillable($chipId);
    }
}
