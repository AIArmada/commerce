<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Enums;

enum PaymentStatus: string
{
    case Success = 'success';
    case Pending = 'pending';
    case Expired = 'expired';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
