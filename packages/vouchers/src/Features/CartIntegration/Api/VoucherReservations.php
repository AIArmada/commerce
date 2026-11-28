<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

interface VoucherReservations
{
    public function reserve(string $code, string $sessionId, ?string $voucherId = null): void;

    public function release(string $code, ?string $sessionId = null, ?string $voucherId = null): void;
}
