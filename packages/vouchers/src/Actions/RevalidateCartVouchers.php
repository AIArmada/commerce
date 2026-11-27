<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Support\VoucherCartMetadata;

final readonly class RevalidateCartVouchers
{
    public function handle(Cart $cart): void
    {
        /** @var array<string> $voucherCodes */
        $voucherCodes = $cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES, []);

        if (empty($voucherCodes)) {
            return;
        }

        $invalidCodes = [];
        $validCodes = [];

        foreach ($voucherCodes as $code) {
            $result = ValidateVoucherCode::run($code, $cart);

            if ($result->isValid) {
                $validCodes[] = $code;
            } else {
                $invalidCodes[$code] = $result->reason ?? 'Voucher is no longer valid';
            }
        }
        if (count($invalidCodes) > 0) {
            foreach (array_keys($invalidCodes) as $invalidCode) {
                RemoveVoucherFromCart::run($cart, (string) $invalidCode);
            }

            $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, $validCodes);
            if (config('vouchers.checkout.block_on_invalid', false)) {
                throw VoucherValidationException::multipleInvalid($invalidCodes);
            }
        }
    }
}
