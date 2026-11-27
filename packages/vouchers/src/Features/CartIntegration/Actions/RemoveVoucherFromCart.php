<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Events\VoucherRemoved;
use AIArmada\Vouchers\Runtime\CartVoucherRemoval;
use Lorisleiva\Actions\Concerns\AsAction;

final class RemoveVoucherFromCart implements CartVoucherRemoval
{
    use AsAction;
    use NormalizesVoucherCodes;

    public function handle(Cart $cart, string $code): void
    {
        $voucherCondition = $this->findVoucherCondition($cart, $code);

        if ($voucherCondition === null) {
            return;
        }

        $conditionName = $voucherCondition->getName();

        if ($cart->getDynamicConditions()->has($conditionName)) {
            $cart->removeDynamicCondition($conditionName);
        }

        $cart->removeCondition($conditionName);

        VoucherRemoved::dispatch($cart, $voucherCondition->getVoucher());
    }

    private function findVoucherCondition(Cart $cart, string $code): ?VoucherCondition
    {
        $normalized = $this->normalizeCode($code);

        $collections = [
            $cart->getDynamicConditions(),
            $cart->getConditions(),
        ];

        foreach ($collections as $collection) {
            foreach ($collection as $condition) {
                if ($condition instanceof VoucherCondition) {
                    if ($this->normalizeCode($condition->getVoucherCode()) === $normalized) {
                        return $condition;
                    }
                } elseif ($condition instanceof CartCondition && $condition->getType() === 'voucher') {
                    $voucherCondition = VoucherCondition::fromCartCondition($condition);

                    if ($voucherCondition !== null && $this->normalizeCode($voucherCondition->getVoucherCode()) === $normalized) {
                        return $voucherCondition;
                    }
                }
            }
        }

        return null;
    }
}
