<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions\Api;

use AIArmada\CommerceSupport\Data\RecordReference;

interface CreateSubscription
{
    public function create(SubscriptionDraft $draft): RecordReference;
}
