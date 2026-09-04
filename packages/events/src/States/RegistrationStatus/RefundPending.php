<?php

declare(strict_types=1);

namespace AIArmada\Events\States\RegistrationStatus;

final class RefundPending extends RegistrationStatus
{
    protected static string $name = 'refund_pending';

    public static function name(): string
    {
        return 'refund_pending';
    }

    public function label(): string
    {
        return 'Refund Pending';
    }
}
