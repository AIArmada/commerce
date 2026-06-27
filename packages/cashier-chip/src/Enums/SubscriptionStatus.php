<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case PastDue = 'past_due';
    case Trialing = 'trialing';
    case Unpaid = 'unpaid';
    case Paused = 'paused';
}
