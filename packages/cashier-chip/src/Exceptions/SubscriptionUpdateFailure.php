<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

final class SubscriptionUpdateFailure extends CashierChipException
{
    public function __construct(string $message = '', public readonly ?string $subscriptionId = null)
    {
        parent::__construct($message);
    }

    public static function incompleteSubscription(string $subscriptionId, string $type): self
    {
        return new self(
            "The subscription \"{$type}\" cannot be updated because it has an incomplete payment.",
            $subscriptionId,
        );
    }

    public static function duplicatePrice(string $subscriptionId, string $type, string $price): self
    {
        return new self(
            "The price \"{$price}\" is already attached to subscription \"{$type}\".",
            $subscriptionId,
        );
    }

    public static function cannotDeleteLastPrice(string $subscriptionId, string $type): self
    {
        return new self(
            "The subscription \"{$type}\" cannot remove its last price.",
            $subscriptionId,
        );
    }
}
