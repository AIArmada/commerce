<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Contracts;

interface ContactMethodNormalizer
{
    public function normalize(string $type, string $value, ?string $countryCode = null): array;
}
