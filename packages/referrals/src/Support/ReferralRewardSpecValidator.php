<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Support;

use InvalidArgumentException;

final class ReferralRewardSpecValidator
{
    /**
     * @param  array<string, mixed>|null  $spec
     * @return array<string, mixed>|null
     */
    public function validate(?array $spec, ?string $defaultCurrency = null): ?array
    {
        if ($spec === null || $spec === []) {
            return null;
        }

        $allowed = ['driver', 'amount_minor', 'points', 'currency', 'voucher'];
        $unknown = array_diff(array_keys($spec), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Referral reward specs contain unsupported keys: ' . implode(', ', $unknown));
        }

        $driver = $spec['driver'] ?? config('referrals.fulfillment.default_driver', 'manual');
        $drivers = ['manual', 'voucher', 'affiliate_cash', 'reward_points'];

        if (! is_string($driver) || ! in_array($driver, $drivers, true)) {
            throw new InvalidArgumentException('Referral reward specs require a supported driver.');
        }

        $amountMinor = $spec['amount_minor'] ?? null;

        if ($amountMinor !== null && (! is_int($amountMinor) || $amountMinor < 0)) {
            throw new InvalidArgumentException('Referral reward amount_minor must be a non-negative integer.');
        }

        $points = $spec['points'] ?? null;

        if ($points !== null && (! is_int($points) || $points < 1)) {
            throw new InvalidArgumentException('Referral reward points must be a positive integer.');
        }

        $currency = $spec['currency'] ?? null;

        if ($currency !== null) {
            if (! is_string($currency)) {
                throw new InvalidArgumentException('Referral reward currency must be a 3-letter code.');
            }

            $currency = mb_strtoupper(mb_trim($currency));
        } elseif ($amountMinor !== null) {
            $currency = mb_strtoupper(mb_trim($defaultCurrency ?? (string) config('referrals.defaults.currency', 'MYR')));
        }

        if ($currency !== null && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Referral reward currency must be an explicit 3-letter code.');
        }

        if ($driver === 'affiliate_cash' && ($amountMinor === null || $amountMinor < 1 || $currency === null)) {
            throw new InvalidArgumentException('Affiliate cash rewards require a positive minor-unit amount and currency.');
        }

        if ($driver === 'reward_points' && $points === null) {
            throw new InvalidArgumentException('Points rewards require a positive points amount.');
        }

        $voucher = $spec['voucher'] ?? null;

        if ($driver === 'voucher') {
            if (! is_array($voucher)) {
                throw new InvalidArgumentException('Voucher rewards require a voucher data array.');
            }

            if (! is_string($voucher['type'] ?? null) || mb_trim($voucher['type']) === '') {
                throw new InvalidArgumentException('Voucher rewards require a voucher type.');
            }

            $voucherValue = $voucher['value'] ?? $amountMinor;

            if (! is_int($voucherValue) || $voucherValue < 0) {
                throw new InvalidArgumentException('Voucher rewards require an integer voucher value.');
            }

            $voucher['value'] = $voucherValue;
        } elseif ($voucher !== null) {
            throw new InvalidArgumentException('Only voucher rewards may include voucher data.');
        }

        return array_filter([
            'driver' => $driver,
            'amount_minor' => $amountMinor,
            'points' => $points,
            'currency' => $currency,
            'voucher' => $voucher,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
