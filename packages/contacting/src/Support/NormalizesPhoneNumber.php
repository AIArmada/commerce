<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Support;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

final class NormalizesPhoneNumber
{
    /**
     * @return array{normalized: string|null, display: string|null}
     */
    public function normalize(?string $phone, ?string $countryCode = null): array
    {
        if ($phone === null) {
            return ['normalized' => null, 'display' => null];
        }

        $phone = mb_trim($phone);

        if ($phone === '') {
            return ['normalized' => null, 'display' => null];
        }

        if (class_exists(PhoneNumber::class)) {
            try {
                $parsed = $countryCode !== null
                    ? new PhoneNumber($phone, $countryCode)
                    : new PhoneNumber($phone);

                return [
                    'normalized' => $parsed->formatE164(),
                    'display' => $parsed->formatInternational(),
                ];
            } catch (Throwable) {
                // Fall through to the lightweight normalizer below.
            }
        }

        $normalized = $this->normalizePhoneNumber($phone, $countryCode);

        return [
            'normalized' => $normalized,
            'display' => $normalized,
        ];
    }

    private function normalizePhoneNumber(string $phone, ?string $countryCode): string
    {
        $phone = preg_replace('/[^\d+]/u', '', $phone) ?? $phone;

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '+')) {
            $digits = preg_replace('/\D+/u', '', mb_substr($phone, 1)) ?? mb_substr($phone, 1);

            return $digits === '' ? '' : '+' . $digits;
        }

        $digits = preg_replace('/\D+/u', '', $phone) ?? $phone;

        if ($digits === '') {
            return '';
        }

        $callingCode = $this->countryCallingCode($countryCode);

        if ($callingCode === null) {
            return $digits;
        }

        $nationalNumber = mb_ltrim($digits, '0');

        if ($nationalNumber === '') {
            return '';
        }

        if (str_starts_with($nationalNumber, $callingCode)) {
            return '+' . $nationalNumber;
        }

        return '+' . $callingCode . $nationalNumber;
    }

    private function countryCallingCode(?string $countryCode): ?string
    {
        if ($countryCode === null) {
            return null;
        }

        return match (mb_strtoupper($countryCode)) {
            'AU' => '61',
            'CA' => '1',
            'GB' => '44',
            'ID' => '62',
            'MY' => '60',
            'NZ' => '64',
            'PH' => '63',
            'SG' => '65',
            'TH' => '66',
            'US' => '1',
            'VN' => '84',
            default => null,
        };
    }
}
