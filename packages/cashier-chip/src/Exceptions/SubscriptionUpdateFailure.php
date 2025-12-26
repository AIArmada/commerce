<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use AIArmada\CashierChip\Subscription;
use Exception;

final class SubscriptionUpdateFailure extends Exception
{
    /**
     * The CHIP subscription instance.
     */
    public ?Subscription $subscription;

    /**
     * Create a new SubscriptionUpdateFailure exception.
     *
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
     */
    public function subscription(): ?Subscription
    {
        return $this->subscription;
    }
}
