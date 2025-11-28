<?php

namespace AIArmada\CashierChip\Exceptions;

use Exception;
use AIArmada\CashierChip\Subscription;

class SubscriptionUpdateFailure extends Exception
{
    /**
     * The CHIP subscription instance.
     *
     * @var \AIArmada\CashierChip\Subscription|null
     */
    public ?Subscription $subscription;

    /**
     * Create a new SubscriptionUpdateFailure exception.
     *
     * @param  string  $message
     * @param  \AIArmada\CashierChip\Subscription|null  $subscription
     * @return void
     */
    public function __construct(string $message = '', ?Subscription $subscription = null)
    {
        parent::__construct($message);

        $this->subscription = $subscription;
    }

    /**
     * Create a new SubscriptionUpdateFailure for an incomplete subscription.
     *
     * @param  \AIArmada\CashierChip\Subscription  $subscription
     * @return static
     */
    public static function incompleteSubscription(Subscription $subscription)
    {
        return new static(
            "The subscription \"{$subscription->type}\" cannot be updated because it has an incomplete payment.",
            $subscription
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure for a duplicate price.
     *
     * @param  \AIArmada\CashierChip\Subscription  $subscription
     * @param  string  $price
     * @return static
     */
    public static function duplicatePrice(Subscription $subscription, string $price)
    {
        return new static(
            "The price \"{$price}\" is already attached to subscription \"{$subscription->type}\".",
            $subscription
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure for attempting to delete the last price.
     *
     * @param  \AIArmada\CashierChip\Subscription  $subscription
     * @return static
     */
    public static function cannotDeleteLastPrice(Subscription $subscription)
    {
        return new static(
            "The subscription \"{$subscription->type}\" cannot remove its last price.",
            $subscription
        );
    }

    /**
     * Get the subscription instance if available.
     *
     * @return \AIArmada\CashierChip\Subscription|null
     */
    public function subscription(): ?Subscription
    {
        return $this->subscription;
    }
}
