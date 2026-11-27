<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Runtime;

interface VoucherReservations
{
    public function reserve(string $code, string $sessionId): void;

    public function release(string $code, ?string $sessionId = null): void;
}
