<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks\Contracts;

interface ProviderWebhookRegistrar
{
    public function resolveProvider(string $provider): ?string;

    public function getSecret(string $provider): ?string;

    public function normalizeProvider(string $provider): string;
}
