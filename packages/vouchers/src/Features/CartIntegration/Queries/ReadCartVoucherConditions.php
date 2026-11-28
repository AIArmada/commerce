<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Queries;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Features\CartIntegration\Api\CartVoucherConditions;

final readonly class ReadCartVoucherConditions implements CartVoucherConditions
{
    use NormalizesVoucherCodes;

    /** @return array<string, VoucherCondition> */
    public function forCart(Cart $cart): array
    {
        $collections = [
            $cart->getDynamicConditions(),
            $cart->getConditions(),
        ];

        $conditions = [];

        foreach ($collections as $collection) {
            foreach ($collection as $condition) {
                if ($condition instanceof VoucherCondition) {
                    $voucherCondition = $condition;
                } elseif ($condition instanceof CartCondition && $condition->getType() === 'voucher') {
                    $voucherCondition = VoucherCondition::fromCartCondition($condition);

                    if ($voucherCondition === null) {
                        continue;
                    }
                } else {
                    continue;
                }

                $conditions[$this->normalizeCode($voucherCondition->getVoucherCode())] = $voucherCondition;
            }
        }

        return $conditions;
    }
}
