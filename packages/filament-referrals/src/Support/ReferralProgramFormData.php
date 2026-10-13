<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Support;

use AIArmada\Referrals\Support\ReferralRewardSpecValidator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ReferralProgramFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toDomain(array $data): array
    {
        foreach ([
            'invite_expiry_days',
            'max_invites_per_referrer',
            'max_qualified_per_referrer_per_month',
        ] as $attribute) {
            $data[$attribute] = self::nullableInteger($data[$attribute] ?? null, $attribute);
        }

        foreach (['referrer_reward', 'referee_reward'] as $side) {
            $state = $data[$side] ?? null;

            if (! is_array($state)) {
                $data[$side] = null;

                continue;
            }

            $driver = is_string($state['driver'] ?? null) ? mb_trim($state['driver']) : '';

            if ($driver === '') {
                $data[$side] = null;

                continue;
            }

            $spec = [
                'driver' => $driver,
                'amount_minor' => self::nullableInteger($state['amount_minor'] ?? null, $side . '.amount_minor'),
                'points' => self::nullableInteger($state['points'] ?? null, $side . '.points'),
                'currency' => is_string($state['currency'] ?? null) && mb_trim($state['currency']) !== ''
                    ? mb_strtoupper(mb_trim($state['currency']))
                    : null,
            ];

            if ($driver === 'voucher') {
                $voucher = [];
                $voucherType = $state['voucher']['type'] ?? null;
                $voucherValue = $state['voucher']['value'] ?? null;

                if (is_string($voucherType) && mb_trim($voucherType) !== '') {
                    $voucher['type'] = mb_trim($voucherType);
                }

                $voucherValue = self::nullableInteger($voucherValue, $side . '.voucher.value');

                if ($voucherValue !== null) {
                    $voucher['value'] = $voucherValue;
                } elseif ($spec['amount_minor'] !== null) {
                    $voucher['value'] = $spec['amount_minor'];
                }

                $spec['voucher'] = $voucher;
            }

            try {
                $data[$side] = app(ReferralRewardSpecValidator::class)->validate(
                    $spec,
                    is_string($data['currency'] ?? null) ? $data['currency'] : null,
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    $side . '.driver' => $exception->getMessage(),
                ]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $spec
     * @return array<string, mixed>
     */
    public static function toForm(?array $spec): array
    {
        return [
            'driver' => $spec['driver'] ?? null,
            'amount_minor' => $spec['amount_minor'] ?? null,
            'points' => $spec['points'] ?? null,
            'currency' => $spec['currency'] ?? null,
            'voucher' => [
                'type' => is_array($spec['voucher'] ?? null) ? ($spec['voucher']['type'] ?? null) : null,
                'value' => is_array($spec['voucher'] ?? null) ? ($spec['voucher']['value'] ?? null) : null,
            ],
        ];
    }

    private static function nullableInteger(mixed $value, string $attribute): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)
            && is_finite($value)
            && floor($value) === $value
            && $value >= PHP_INT_MIN
            && $value <= PHP_INT_MAX) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw ValidationException::withMessages([
            $attribute => 'The value must be a whole number.',
        ]);
    }
}
