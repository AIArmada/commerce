<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\CartIntegration\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\Concerns\NormalizesVoucherCodes;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Support\VoucherCartMetadata;

final readonly class RevalidateCartVouchers
{
    use NormalizesVoucherCodes;

    public function __construct(private VoucherValidation $validator) {}

    public function handle(Cart $cart): void
    {
        $metadata = $cart->getMetadata(VoucherCartMetadata::VOUCHER_CODES, []);
        $metadataCodes = [];

        if (is_array($metadata)) {
            foreach ($metadata as $code) {
                if (is_string($code) && mb_trim($code) !== '') {
                    $metadataCodes[] = $this->normalizeCode($code);
                }
            }
        }

        $conditionNames = [];
        $conditionSnapshots = [];

        foreach ([$cart->getDynamicConditions(), $cart->getStoredConditions()] as $conditions) {
            foreach ($conditions as $condition) {
                if ($condition->getType() !== 'voucher') {
                    continue;
                }

                $code = $condition->getAttributes()['voucher_code'] ?? null;

                if (is_string($code) && mb_trim($code) !== '') {
                    $normalized = $this->normalizeCode($code);
                    $conditionNames[$normalized][$condition->getName()] = true;
                    $conditionSnapshots[$normalized][] = $condition->getAttributes()['voucher_data'] ?? null;
                }
            }
        }

        $codes = array_unique([...$metadataCodes, ...array_keys($conditionNames)]);
        $invalidCodes = [];

        foreach ($codes as $code) {
            $result = $this->validator->validate((string) $code, $cart);

            if (! $result->isValid) {
                $invalidCodes[$code] = $result->reason ?? 'Voucher is no longer valid';

                continue;
            }

            foreach ($conditionSnapshots[$code] ?? [] as $snapshot) {
                if (! $this->matchesCurrentVoucher($snapshot, $result->getDetail('voucher'))) {
                    $invalidCodes[$code] = 'Voucher terms have changed. Apply the voucher again.';

                    break;
                }
            }
        }

        if ($invalidCodes === []) {
            return;
        }

        if ($metadataCodes !== []) {
            $cart->setMetadata(VoucherCartMetadata::VOUCHER_CODES, array_values(array_filter(
                $metadataCodes,
                static fn (string $code): bool => ! isset($invalidCodes[$code]),
            )));
        }

        foreach (array_keys($invalidCodes) as $code) {
            RemoveVoucherFromCart::run($cart, (string) $code);

            foreach (array_keys($conditionNames[$code] ?? []) as $name) {
                if ($cart->getDynamicConditions()->has($name)) {
                    $cart->removeDynamicCondition($name);
                }

                $cart->removeCondition($name);
            }
        }

        if (config('vouchers.checkout.block_on_invalid', false)) {
            throw VoucherValidationException::multipleInvalid($invalidCodes);
        }
    }
    private function matchesCurrentVoucher(mixed $snapshot, mixed $current): bool
    {
        if (! is_array($snapshot) || ! is_array($current)) {
            return false;
        }

        foreach (['id', 'type', 'value', 'value_config', 'currency', 'max_discount', 'target_definition', 'credit_destination', 'credit_delay_hours'] as $field) {
            if (! array_key_exists($field, $snapshot) || ! array_key_exists($field, $current) || $snapshot[$field] !== $current[$field]) {
                return false;
            }
        }

        return true;
    }
}
