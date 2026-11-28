<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Features\CartIntegration\Api\CartVoucherApplicator;
use AIArmada\Vouchers\Features\CartIntegration\Api\CartVoucherConditions;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class ApplyVoucherToCart implements CartVoucherApplicator
{
    use AsAction;
    use NormalizesVoucherCodes;

    public function handle(
        Cart $cart,
        string $code,
        ?StackingPolicyInterface $policy = null,
        int $order = 100,
        ?string $expectedVoucherId = null,
    ): VoucherCondition {
        $code = $this->normalizeCode($code);

        $validationResult = app(VoucherValidation::class)->validate($code, $cart);
        if (! $validationResult->isValid) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' cannot be applied: {$validationResult->reason}"
            );
        }

        $applied = app(CartVoucherConditions::class)->forCart($cart);

        if (isset($applied[$code])) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' is already applied to this cart"
            );
        }

        $snapshot = $validationResult->getDetail('voucher');
        if (! is_array($snapshot) || ! is_string($snapshot['id'] ?? null) || $snapshot['id'] === '') {
            throw new InvalidVoucherException(
                "Voucher '{$code}' not found",
            );
        }

        if ($expectedVoucherId !== null && ! $validationResult->matchesVoucher($expectedVoucherId)) {
            throw new InvalidVoucherException("Voucher '{$code}' no longer matches the selected voucher.");
        }

        $voucherData = VoucherData::fromArray($snapshot);
        $voucherCondition = new VoucherCondition($voucherData, $order);
        $existingVouchers = collect(array_values($applied));

        $policy ??= app(StackingPolicyInterface::class);
        $decision = $policy->canAdd(
            $voucherCondition,
            $existingVouchers,
            $cart,
        );

        $conflicting = null;

        if ($decision->isDenied()) {
            $conflicting = $decision->conflictsWith;

            if (! $policy->isAutoReplaceEnabled() || $conflicting === null) {
                throw new InvalidVoucherException($decision->getReason());
            }

            $remaining = $existingVouchers->reject(
                static fn (VoucherCondition $existing): bool => $existing->getVoucherCode() === $conflicting->getVoucherCode()
            )->values();

            if ($remaining->count() === $existingVouchers->count() || $policy->canAdd($voucherCondition, $remaining, $cart)->isDenied()) {
                throw new InvalidVoucherException($decision->getReason());
            }
        }

        VoucherRulesFactory::attachTo($cart);

        try {
            $cart->registerDynamicCondition(
                $voucherCondition->toCartCondition(),
                null,
                $voucherCondition->getRuleFactoryKey(),
                $voucherCondition->getRuleFactoryContext()
            );
        } catch (Throwable $exception) {
            throw new InvalidVoucherException(
                "Voucher '{$code}' cannot be applied: {$exception->getMessage()}",
                previous: $exception
            );
        }

        if ($conflicting !== null) {
            RemoveVoucherFromCart::run($cart, $conflicting->getVoucherCode());
        }

        VoucherApplied::dispatch($cart, $voucherData);

        return $voucherCondition;
    }
}
