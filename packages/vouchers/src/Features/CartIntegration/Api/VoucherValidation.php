<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Api;

use AIArmada\Vouchers\Data\VoucherValidationResult;

interface VoucherValidation
{
    public function validate(string $code, mixed $cart): VoucherValidationResult;
}
