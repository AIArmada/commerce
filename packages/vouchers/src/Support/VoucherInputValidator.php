<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Support;

use AIArmada\Vouchers\Data\VoucherType;
use Akaunting\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class VoucherInputValidator
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function validate(array $data): void
    {
        /** @var array<string, string> $errors */
        $errors = [];

        $type = $data['type'] ?? null;

        if ($type instanceof VoucherType) {
            $voucherType = $type;
        } elseif (is_string($type) && VoucherType::tryFrom($type) !== null) {
            $voucherType = VoucherType::from($type);
        } else {
            $voucherType = null;
            $errors['type'] = 'A valid voucher type is required.';
        }

        $value = $this->coercedInt($data['value'] ?? null);

        if ($value === null) {
            $errors['value'] = 'A voucher value is required.';
        } elseif ($value < 0) {
            $errors['value'] = 'The voucher value cannot be negative.';
        } elseif ($voucherType === VoucherType::Percentage && $value > 10000) {
            $errors['value'] = 'Percentage values are basis points and cannot exceed 10000.';
        }

        if (isset($data['currency']) && $data['currency'] !== '') {
            $currency = is_string($data['currency']) ? mb_strtoupper($data['currency']) : '';

            if (! preg_match('/^[A-Z]{3}$/', $currency) || ! array_key_exists($currency, Currency::getCurrencies())) {
                $errors['currency'] = 'The currency must be a supported 3-letter code.';
            }
        }

        foreach (['usage_limit', 'max_uses', 'usage_limit_per_user', 'max_uses_per_user'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $limit = $this->coercedInt($data[$key]);

            if ($limit === null || $limit < 0) {
                $errors[$key] = 'Usage limits must be zero or a positive integer.';
            }
        }

        foreach (['min_order_value', 'min_cart_value', 'max_discount_value', 'max_discount', 'credit_delay_hours', 'creditDelayHours'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            $amount = $this->coercedInt($data[$key]);

            if ($amount === null || $amount < 0) {
                $errors[$key] = 'Amount and delay values must be zero or a positive integer.';
            }
        }

        $startsAt = $this->parsedDate($data['starts_at'] ?? null);
        $expiresAt = $this->parsedDate($data['expires_at'] ?? null);

        if (array_key_exists('starts_at', $data) && $data['starts_at'] !== null && $startsAt === null) {
            $errors['starts_at'] = 'The start date is not a valid date.';
        }

        if (array_key_exists('expires_at', $data) && $data['expires_at'] !== null && $expiresAt === null) {
            $errors['expires_at'] = 'The expiry date is not a valid date.';
        }

        if ($startsAt !== null && $expiresAt !== null && $startsAt->greaterThanOrEqualTo($expiresAt)) {
            $errors['expires_at'] = 'The expiry date must be after the start date.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function coercedInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', mb_trim($value))) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);

            return $parsed === false ? null : $parsed;
        }

        return null;
    }

    private function parsedDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
