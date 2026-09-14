<?php

declare(strict_types=1);

namespace AIArmada\Communications\Webhooks\Registrars;

use AIArmada\Communications\Webhooks\Contracts\ProviderWebhookRegistrar;
use RuntimeException;

final class ProviderWebhookRegistrarService implements ProviderWebhookRegistrar
{
    public function supports(string $provider): bool
    {
        $provider = $this->normalizeProvider($provider);
        $config = config("communications.webhooks.providers.{$provider}");

        return is_array($config) && ($config['enabled'] ?? true) !== false;
    }

    public function resolveProvider(string $provider): ?string
    {
        $provider = $this->normalizeProvider($provider);
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
        $provider = $this->normalizeProvider($provider);
        $config = config("communications.webhooks.providers.{$provider}");

        if (is_array($config) && is_string($config['secret'] ?? null) && $config['secret'] !== '') {
            return $config['secret'];
        }

        return null;
    }

    public function getAlgorithm(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);
        $config = config("communications.webhooks.providers.{$provider}");

        $algorithm = is_array($config) && is_string($config['algorithm'] ?? null) && $config['algorithm'] !== ''
            ? mb_strtolower($config['algorithm'])
            : 'sha256';

        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new RuntimeException("Unsupported webhook signature algorithm [{$algorithm}] for provider [{$provider}].");
        }

        return $algorithm;
    }

    public function getSignatureHeader(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);
        $config = config("communications.webhooks.providers.{$provider}");

        if (is_array($config) && is_string($config['signature_header'] ?? null) && mb_trim($config['signature_header']) !== '') {
            return $config['signature_header'];
        }

        return 'X-Webhook-Signature';
    }

    public function normalizeProvider(string $provider): string
    {
        return str_replace(['-', ' '], '_', mb_strtolower(mb_trim($provider)));
    }
}
