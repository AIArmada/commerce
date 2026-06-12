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

        try {
            $parsed = $countryCode
                ? new PhoneNumber($phone, $countryCode)
                : new PhoneNumber($phone);

            return [
                'normalized' => $parsed->formatE164(),
                'display' => $parsed->formatInternational(),
            ];
        } catch (Throwable) {
            return [
                'normalized' => $phone,
                'display' => $phone,
            ];
        }
    }
}
