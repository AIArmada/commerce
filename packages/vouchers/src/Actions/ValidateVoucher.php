<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Services\VoucherValidator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate a voucher code against a cart.
 */
final class ValidateVoucher
{
    use AsAction;

    public function __construct(
        private readonly VoucherValidator $validator,
    ) {}

    /**
     * Validate a voucher code.
     */
    public function handle(string $code, mixed $cart): VoucherValidationResult
    {
        return $this->validator->validate($code, $cart);
    }
}
