<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Muted = 'muted';
    case Unsubscribed = 'unsubscribed';
    case Expired = 'expired';
}
