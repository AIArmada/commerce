<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Contracts;

interface ContactMethodNormalizer
{
    /** @return array{normalized_value: string|null, display_value: string|null} */
    public function normalize(string $type, string $value, ?string $countryCode = null): array;
}
