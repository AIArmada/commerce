<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Services\VoucherValidator;
use Lorisleiva\Actions\Concerns\AsAction;

final class ValidateVoucherCode
{
    use AsAction;

    public function __construct(
        private readonly VoucherValidator $validator
    ) {}

    public function handle(string $code, mixed $cart): VoucherValidationResult
    {
        return $this->validator->validate($code, $cart);
    }
}
