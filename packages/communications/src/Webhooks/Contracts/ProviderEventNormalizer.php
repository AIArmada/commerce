<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks\Contracts;

use AIArmada\Communications\Data\ProviderEventData;

interface ProviderEventNormalizer
{
    public function normalize(string $provider, array $payload): ProviderEventData;

    public function supports(string $provider): bool;
}
