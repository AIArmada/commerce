<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Exceptions\WebhookVerificationException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Verify webhook signature using Request object
     */
    public function verifySignature(Request|string $payloadOrRequest, ?string $signature = null, ?string $publicKey = null): bool
    {
        if ($payloadOrRequest instanceof Request) {
            $payload = $payloadOrRequest->getContent();
            $signature ??= $payloadOrRequest->header('X-Signature');
        } else {
            $payload = (string) $payloadOrRequest;
        }

        // Always verify signatures in production for security
        // Only allow disabling in non-production environments (testing, development)
        $shouldVerify = config('chip.webhooks.verify_signature', true);
        $isProduction = app()->environment('production');

        if (! $shouldVerify && $isProduction) {
            throw new WebhookVerificationException('Signature verification cannot be disabled in production environment');
        }

        if (! $shouldVerify) {
            return true;
        }

        $publicKey = $publicKey ?? $this->getPublicKey();

        if (! $signature) {
            throw new WebhookVerificationException('Missing signature header');
        }

        if (! $publicKey) {
            throw new WebhookVerificationException('No public key configured');
        }

        try {
            $pemKey = str_contains($publicKey, 'BEGIN PUBLIC KEY')
                ? $publicKey
                : "-----BEGIN PUBLIC KEY-----\n".chunk_split(str_replace(["\n", "\r", ' '], '', $publicKey), 64, "\n").'-----END PUBLIC KEY-----';

            $publicKeyResource = openssl_pkey_get_public($pemKey);
            if (! $publicKeyResource) {
                throw new WebhookVerificationException('Invalid public key format');
            }

            $decodedSignature = base64_decode($signature, true);
            if ($decodedSignature === false) {
                throw new WebhookVerificationException('Signature is not valid base64');
            }

            $verified = openssl_verify($payload, $decodedSignature, $publicKeyResource, OPENSSL_ALGO_SHA256);

            return $verified === 1;
        } catch (Exception $e) {
            Log::channel(config('chip.logging.channel'))
                ->error('Webhook signature verification failed', [
                    'error' => $e->getMessage(),
                ]);

            throw new WebhookVerificationException('Signature verification failed: '.$e->getMessage());
        }
    }

    /**
     * Get public key for webhook signature verification
     *
     * @param  string|null  $webhookId  Optional webhook ID for webhook-specific key
     * @return string Public key in PEM format
     *
     * @throws WebhookVerificationException If no public key is available
     */
    public function getPublicKey(?string $webhookId = null): string
    {
        $cacheKey = config('chip.cache.prefix').'public_key'.($webhookId ? ":{$webhookId}" : '');

        return Cache::remember(
            $cacheKey,
            config('chip.cache.ttl.public_key', 86400),
            function () use ($webhookId) {
                try {
                    // For webhook-specific requests, try webhook_keys first
                    if ($webhookId) {
                        $configuredKeys = (array) config('chip.webhooks.webhook_keys', []);

                        if (isset($configuredKeys[$webhookId]) && $configuredKeys[$webhookId] !== '') {
                            return (string) $configuredKeys[$webhookId];
                        }

                        // Try fetching webhook-specific key from CHIP API
                        $webhook = app(ChipCollectService::class)->getWebhook($webhookId);
                        $publicKey = (string) ($webhook['public_key'] ?? '');

                        if ($publicKey !== '') {
                            return $publicKey;
                        }
                    }

                    // For general requests or when webhook-specific key not found,
                    // use company public key (mandatory, no fallback)
                    if (! $webhookId) {
                        $companyKey = config('chip.webhooks.company_public_key');
                        if ($companyKey) {
                            return (string) $companyKey;
                        }

                        // Try fetching company public key from CHIP API
                        $response = app(ChipCollectClient::class)->get('public_key/');

                        $publicKey = is_array($response)
                            ? (string) ($response['public_key'] ?? '')
                            : (string) $response;

                        if ($publicKey !== '') {
                            return $publicKey;
                        }

                        throw new WebhookVerificationException('Company public key is required but not configured. Set CHIP_COMPANY_PUBLIC_KEY environment variable.');
                    }
                } catch (WebhookVerificationException $e) {
                    throw $e;
                } catch (Exception $e) {
                    Log::channel(config('chip.logging.channel'))
                        ->warning('Unable to resolve CHIP public key from API, using fallback', [
                            'webhook_id' => $webhookId,
                            'error' => $e->getMessage(),
                        ]);

                    // Fallback for webhook-specific keys only
                    if ($webhookId) {
                        $fallbackKeys = (array) config('chip.webhooks.webhook_keys', []);
                        if (isset($fallbackKeys[$webhookId])) {
                            return (string) $fallbackKeys[$webhookId];
                        }

                        throw new WebhookVerificationException("No public key available for webhook ID: {$webhookId}");
                    }

                    // For company key, check config again
                    $companyKey = config('chip.webhooks.company_public_key');
                    if ($companyKey) {
                        return (string) $companyKey;
                    }

                    throw new WebhookVerificationException('Company public key is required but not configured. Set CHIP_COMPANY_PUBLIC_KEY environment variable.');
                }

                // Should not reach here, but ensure we don't return empty string
                throw new WebhookVerificationException('Unable to retrieve public key');
            }
        );
    }

    public function parsePayload(string $payload): object
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookVerificationException('Invalid JSON payload');
        }

        return (object) $data;
    }
}
