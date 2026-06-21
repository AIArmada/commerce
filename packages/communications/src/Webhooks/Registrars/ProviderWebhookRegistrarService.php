<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks\Registrars;

use AIArmada\Communications\Webhooks\Contracts\ProviderWebhookRegistrar;

final class ProviderWebhookRegistrarService implements ProviderWebhookRegistrar
{
    public function resolveProvider(string $provider): ?string
    {
        $config = config("communications.webhooks.providers.{$provider}");

        if (! is_array($config)) {
            return null;
        }

        return is_string($config['owner_type'] ?? null) && $config['owner_type'] !== ''
            ? $config['owner_type']
            : null;
    }

    public function getSecret(string $provider): ?string
    {
        $config = config("communications.webhooks.providers.{$provider}");

        if (is_array($config) && is_string($config['secret'] ?? null) && $config['secret'] !== '') {
            return $config['secret'];
        }

        $secret = config("services.webhooks.{$provider}.secret");

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $fallback = config('services.webhooks.secret');

        return is_string($fallback) && $fallback !== ''
            ? $fallback
            : null;
    }

    public function normalizeProvider(string $provider): string
    {
        return str_replace(['-', ' '], '_', mb_strtolower(mb_trim($provider)));
    }
}
