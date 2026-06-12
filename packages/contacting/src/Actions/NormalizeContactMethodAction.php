<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Support\NormalizesEmailAddress;
use AIArmada\Contacting\Support\NormalizesPhoneNumber;
use AIArmada\Contacting\Support\NormalizesUrl;

final class NormalizeContactMethodAction
{
    public function __construct(
        private readonly NormalizesEmailAddress $emailNormalizer,
        private readonly NormalizesPhoneNumber $phoneNormalizer,
        private readonly NormalizesUrl $urlNormalizer,
    ) {}

    /**
     * @return array{normalized_value: string|null, display_value: string|null}
     */
    public function execute(string $type, string $value, ?string $countryCode = null): array
    {
        return match ($type) {
            'email' => $this->normalizeEmail($value),
            'phone', 'mobile', 'whatsapp' => $this->normalizePhone($value, $countryCode),
            'website' => $this->normalizeWebsite($value),
            default => ['normalized_value' => $value, 'display_value' => $value],
        };
    }

    /**
     * @return array{normalized_value: string|null, display_value: string|null}
     */
    private function normalizeEmail(string $value): array
    {
        $normalized = $this->emailNormalizer->normalize($value);

        return [
            'normalized_value' => $normalized,
            'display_value' => $normalized,
        ];
    }

    /**
     * @return array{normalized_value: string|null, display_value: string|null}
     */
    private function normalizePhone(string $value, ?string $countryCode): array
    {
        $result = $this->phoneNormalizer->normalize($value, $countryCode);

        return [
            'normalized_value' => $result['normalized'],
            'display_value' => $result['display'] ?? $value,
        ];
    }

    /**
     * @return array{normalized_value: string|null, display_value: string|null}
     */
    private function normalizeWebsite(string $value): array
    {
        $normalized = $this->urlNormalizer->normalize($value);

        return [
            'normalized_value' => $normalized,
            'display_value' => $normalized,
        ];
    }
}
