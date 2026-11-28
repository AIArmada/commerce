<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Events\VoucherRemoved;
use AIArmada\Vouchers\Features\CartIntegration\Api\CartVoucherRemoval;
use AIArmada\Vouchers\Support\VoucherCartMetadata;
use Lorisleiva\Actions\Concerns\AsAction;

final class RemoveVoucherFromCart implements CartVoucherRemoval
{
    use AsAction;
    use NormalizesVoucherCodes;

    public function handle(Cart $cart, string $code): void
    {
        $normalized = $this->normalizeCode($code);
        $metadata = $cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES, []);
        $conditions = [];

        foreach ([$cart->getDynamicConditions(), $cart->getStoredConditions()] as $collection) {
            foreach ($collection as $condition) {
                $candidate = $condition->getAttribute('voucher_code');

                if ($condition->getType() === 'voucher' && is_string($candidate)
                    && $this->normalizeCode($candidate) === $normalized) {
                    $conditions[$condition->getName()] = $condition;
                }
            }
        }

        if (is_array($metadata) && $metadata !== []) {
            $remaining = array_values(array_filter(
                $metadata,
                fn (mixed $candidate): bool => is_string($candidate) && $this->normalizeCode($candidate) !== $normalized
            ));

            if ($remaining !== $metadata) {
                $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, $remaining);
            }
        }

        $voucher = null;

        foreach ($conditions as $name => $condition) {
            $voucher ??= VoucherCondition::fromCartCondition($condition)?->getVoucher();

            if ($cart->getDynamicConditions()->has($name)) {
                $cart->removeDynamicCondition($name);
            }

            $cart->removeCondition($name);
        }

        if ($voucher !== null) {
            VoucherRemoved::dispatch($cart, $voucher);
        }
    }
}
