<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Exceptions\WebhookVerificationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookService
{
    /**
     * Verify webhook signature using Request object
     */
    public function verifySignature(Request | string $payloadOrRequest, ?string $signature = null, ?string $publicKey = null): bool
    {
        if (! $this->assertSignatureVerificationEnabled()) {
            return true;
        }

        [$payload, $signature] = $this->resolveSignatureInput($payloadOrRequest, $signature);

        return $this->verifyPayloadSignature(
            payload: $payload,
            signature: $signature,
            publicKeys: $this->resolveVerificationPublicKeys($payloadOrRequest, $publicKey),
            algorithm: OPENSSL_ALGO_SHA256,
        );
    }

    /**
     * Verify a CHIP Collect purchase success callback.
     *
     * Success callbacks use the company-wide public key returned by
     * GET /public_key/. Registered webhooks use their own Webhook.public_key
     * and are verified by verifySignature().
     */
    public function verifySuccessCallbackSignature(Request | string $payloadOrRequest, ?string $signature = null, ?string $publicKey = null): bool
    {
        if (! $this->assertSignatureVerificationEnabled()) {
            return true;
        }

        [$payload, $signature] = $this->resolveSignatureInput($payloadOrRequest, $signature);

        return $this->verifyPayloadSignature(
            payload: $payload,
            signature: $signature,
            publicKeys: $this->resolveSuccessCallbackPublicKeys($publicKey),
            algorithm: OPENSSL_ALGO_SHA256,
        );
    }

    /**
     * Verify a CHIP Send webhook signature.
     *
     * CHIP Send uses the dedicated webhook public key and SHA-512. It does not
     * use the Collect company key or the Collect webhook event envelope.
     */
    public function verifySendSignature(Request | string $payloadOrRequest, ?string $signature = null, ?string $publicKey = null): bool
    {
        if (! $this->assertSignatureVerificationEnabled()) {
            return true;
        }

        [$payload, $signature] = $this->resolveSignatureInput($payloadOrRequest, $signature);

        return $this->verifyPayloadSignature(
            payload: $payload,
            signature: $signature,
            publicKeys: $this->resolveSendVerificationPublicKeys($payloadOrRequest, $publicKey),
            algorithm: OPENSSL_ALGO_SHA512,
        );
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
        $cacheKey = config('chip.cache.prefix') . 'public_key' . ($webhookId ? ":{$webhookId}" : '');

        return Cache::remember(
            $cacheKey,
            config('chip.cache.ttl.public_key', 86400),
            function () use ($webhookId) {
                try {
                    if ($webhookId !== null) {
                        $configuredKeys = (array) config('chip.webhooks.collect.webhook_keys', []);
                        $configuredKey = $configuredKeys[$webhookId] ?? null;

                        if (is_string($configuredKey) && $configuredKey !== '') {
                            return $configuredKey;
                        }

                        $webhook = app(ChipCollectService::class)->getWebhook($webhookId);
                        $publicKey = $webhook['public_key'] ?? null;

                        if (! is_string($publicKey) || $publicKey === '') {
                            throw new WebhookVerificationException("CHIP Collect webhook {$webhookId} did not return a public key.");
                        }

                        return $publicKey;
                    }

                    $companyKey = config('chip.collect.public_key');

                    if (is_string($companyKey) && $companyKey !== '') {
                        return $companyKey;
                    }

                    $response = app(ChipCollectClient::class)->get('public_key/');

                    if (! is_string($response) || $response === '') {
                        throw new WebhookVerificationException('CHIP Collect public_key/ did not return a PEM string.');
                    }

                    return $response;
                } catch (WebhookVerificationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    throw new WebhookVerificationException(
                        'Unable to retrieve the CHIP Collect public key.',
                        0,
                        $exception,
                    );
                }
            }
        );
    }

    /**
     * Resolve the dedicated CHIP Send webhook public key.
     *
     * @throws WebhookVerificationException If the configured webhook cannot be resolved
     */
    public function getSendPublicKey(?int $webhookId = null): string
    {
        $configuredWebhookId = $webhookId ?? $this->configuredSendWebhookId();

        if ($configuredWebhookId === null) {
            throw new WebhookVerificationException('A CHIP Send webhook ID or public key is required.');
        }

        $configuredKeys = (array) config('chip.webhooks.send.webhook_keys', []);
        $configuredKey = $configuredKeys[$configuredWebhookId] ?? null;

        if (is_string($configuredKey) && $configuredKey !== '') {
            return $configuredKey;
        }

        $cacheKey = config('chip.cache.prefix') . "send_public_key:{$configuredWebhookId}";

        return Cache::remember(
            $cacheKey,
            config('chip.cache.ttl.public_key', 86400),
            function () use ($configuredWebhookId): string {
                try {
                    $webhook = app(ChipSendService::class)->getSendWebhook($configuredWebhookId);

                    if ($webhook->public_key === '') {
                        throw new WebhookVerificationException("CHIP Send webhook {$configuredWebhookId} did not return a public key.");
                    }

                    return $webhook->public_key;
                } catch (WebhookVerificationException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    throw new WebhookVerificationException(
                        'Unable to retrieve the CHIP Send webhook public key.',
                        0,
                        $exception,
                    );
                }
            },
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

    /**
     * @return array<int, string>
     */
    protected function resolveVerificationPublicKeys(Request | string $payloadOrRequest, ?string $publicKey = null): array
    {
        if (is_string($publicKey) && $publicKey !== '') {
            return [$publicKey];
        }

        if (! $payloadOrRequest instanceof Request) {
            throw WebhookVerificationException::missingPublicKey();
        }

        $candidateKeys = [];

        foreach ((array) config('chip.webhooks.collect.webhook_keys', []) as $configuredKey) {
            if (is_string($configuredKey) && $configuredKey !== '') {
                $candidateKeys[] = $configuredKey;
            }
        }

        if ($candidateKeys === []) {
            throw new WebhookVerificationException('No CHIP Collect webhook public key is configured.');
        }

        return array_values(array_unique($candidateKeys));
    }

    /**
     * @return array<int, string>
     */
    protected function resolveSuccessCallbackPublicKeys(?string $publicKey = null): array
    {
        if (is_string($publicKey) && $publicKey !== '') {
            return [$publicKey];
        }

        return [$this->getPublicKey()];
    }

    /**
     * @return array<int, string>
     */
    protected function resolveSendVerificationPublicKeys(Request | string $payloadOrRequest, ?string $publicKey = null): array
    {
        if (is_string($publicKey) && $publicKey !== '') {
            return [$publicKey];
        }

        if (! $payloadOrRequest instanceof Request) {
            throw new WebhookVerificationException('A CHIP Send webhook public key is required.');
        }

        $configuredWebhookId = $this->configuredSendWebhookId();

        if ($configuredWebhookId !== null) {
            return [$this->getSendPublicKey($configuredWebhookId)];
        }

        $candidateKeys = array_values(array_filter(
            (array) config('chip.webhooks.send.webhook_keys', []),
            static fn (mixed $configuredKey): bool => is_string($configuredKey) && $configuredKey !== '',
        ));

        if ($candidateKeys === []) {
            throw new WebhookVerificationException('No CHIP Send webhook public key is configured.');
        }

        return array_values(array_unique($candidateKeys));
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveSignatureInput(Request | string $payloadOrRequest, ?string $signature): array
    {
        if ($payloadOrRequest instanceof Request) {
            return [$payloadOrRequest->getContent(), $signature ?? $payloadOrRequest->header('X-Signature')];
        }

        return [(string) $payloadOrRequest, $signature];
    }

    /**
     * @param  array<int, string>  $publicKeys
     */
    private function verifyPayloadSignature(string $payload, ?string $signature, array $publicKeys, int $algorithm): bool
    {
        if (! $signature) {
            throw new WebhookVerificationException('Missing signature header');
        }

        try {
            $decodedSignature = base64_decode($signature, true);
            if ($decodedSignature === false) {
                throw new WebhookVerificationException('Signature is not valid base64');
            }

            if ($publicKeys === []) {
                throw new WebhookVerificationException('No public key configured');
            }

            $attemptedVerification = false;
            $lastException = null;

            foreach ($publicKeys as $candidateKey) {
                try {
                    $publicKeyResource = openssl_pkey_get_public($this->normalizePublicKey($candidateKey));

                    if (! $publicKeyResource) {
                        throw new WebhookVerificationException('Invalid public key format');
                    }

                    $attemptedVerification = true;

                    if (openssl_verify($payload, $decodedSignature, $publicKeyResource, $algorithm) === 1) {
                        return true;
                    }
                } catch (WebhookVerificationException $exception) {
                    $lastException = $exception;
                }
            }

            if (! $attemptedVerification && $lastException instanceof WebhookVerificationException) {
                throw $lastException;
            }

            return false;
        } catch (Throwable $e) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->error('Webhook signature verification failed', [
                    'error' => $e->getMessage(),
                ]);

            throw new WebhookVerificationException('Signature verification failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function assertSignatureVerificationEnabled(): bool
    {
        $shouldVerify = config('chip.webhooks.verify_signature', true);

        if (! $shouldVerify && app()->environment('production')) {
            throw new WebhookVerificationException('Signature verification cannot be disabled in production environment');
        }

        if (! $shouldVerify) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('Webhook signature verification is disabled', [
                    'environment' => app()->environment(),
                ]);

            return false;
        }

        return true;
    }

    private function configuredSendWebhookId(): ?int
    {
        $webhookId = config('chip.webhooks.send.webhook_id');

        if (is_int($webhookId)) {
            return $webhookId;
        }

        if (is_string($webhookId) && ctype_digit($webhookId)) {
            return (int) $webhookId;
        }

        return null;
    }

    protected function normalizePublicKey(string $publicKey): string
    {
        return str_contains($publicKey, 'BEGIN PUBLIC KEY')
            ? $publicKey
            : "-----BEGIN PUBLIC KEY-----\n" . chunk_split(str_replace(["\n", "\r", ' '], '', $publicKey), 64, "\n") . '-----END PUBLIC KEY-----';
    }
}
