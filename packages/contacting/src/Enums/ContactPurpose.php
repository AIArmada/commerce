<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Enums;

enum ContactPurpose: string
{
    case General = 'general';
    case Admin = 'admin';
    case Support = 'support';
    case Billing = 'billing';
    case Sales = 'sales';
    case Registration = 'registration';
    case Media = 'media';
    case Donation = 'donation';
    case Partnership = 'partnership';
    case Emergency = 'emergency';
    case Privacy = 'privacy';
    case Other = 'other';
}
