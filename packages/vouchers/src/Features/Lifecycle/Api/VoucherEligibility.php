<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Api;

use AIArmada\Vouchers\Data\VoucherValidationResult;

interface VoucherEligibility
{
    public function validate(string $code, mixed $cartCurrency, ?string $actorType, ?string $actorId): VoucherValidationResult;
}
