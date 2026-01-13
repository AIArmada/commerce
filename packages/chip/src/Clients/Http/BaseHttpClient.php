<?php

declare(strict_types=1);

namespace AIArmada\Chip\Clients\Http;

use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipRateLimitException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

abstract class BaseHttpClient
{
    /**
     * @param  array<string, mixed>  $retryConfig
     */
    public function __construct(
        protected int $timeout = 30,
        protected array $retryConfig = [],
    ) {}

    abstract protected function resolveBaseUrl(): string;

    /**
     * Perform the low level request. Implementations may customise headers or payload handling.
     */
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    abstract protected function sendRequest(string $method, string $url, array $data, array $headers = []): Response;

    /**
     * Convert non-successful responses into domain specific exceptions.
     */
    abstract protected function handleFailedResponse(Response $response): never;

    /**
     * Perform an HTTP request while handling retries, logging, and error wrapping.
     */
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    final public function request(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $this->checkRateLimit();

        $url = $this->buildUrl($endpoint);

        $this->logRequest($method, $url, $data);

        $attempts = max(1, (int) ($this->retryConfig['attempts'] ?? 1));
        $delayMilliseconds = max(0, (int) ($this->retryConfig['delay'] ?? 0));

        $response = null;

        try {
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                try {
                    $response = $this->sendRequest($method, $url, $data, $headers);

                    if ($response->failed() && $attempt < $attempts && $this->shouldRetry(null, $response)) {
                        usleep($delayMilliseconds * 1000);

                        continue;
                    }

                    if ($response->failed()) {
                        $this->handleFailedResponse($response);
                    }

                    break;
                } catch (Exception $exception) {
                    if ($attempt >= $attempts || ! $this->shouldRetry($exception, null)) {
                        throw $exception;
                    }

                    usleep($delayMilliseconds * 1000);
                }
            }

            $this->logResponse($response);

            return $response->json() ?? [];
        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    protected function buildUrl(string $endpoint): string
    {
        return mb_rtrim($this->resolveBaseUrl(), '/') . '/' . mb_ltrim($endpoint, '/');
    }

    protected function checkRateLimit(): void
    {
        if (! $this->rateLimitEnabled()) {
            return;
        }

        $key = $this->rateLimitKey();
        $maxAttempts = $this->rateLimitMaxAttempts();
        $decaySeconds = $this->rateLimitDecaySeconds();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new ChipRateLimitException($retryAfter);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    protected function rateLimitEnabled(): bool
    {
        return (bool) config('chip.http.rate_limit.enabled', true);
    }

    protected function rateLimitKey(): string
    {
        return 'chip_api:' . static::class;
    }

    protected function rateLimitMaxAttempts(): int
    {
        return (int) config('chip.http.rate_limit.max_attempts', 60);
    }

    protected function rateLimitDecaySeconds(): int
    {
        return (int) config('chip.http.rate_limit.decay_seconds', 60);
    }

    protected function shouldRetry(?Throwable $exception, ?Response $response): bool
    {
        if ($exception !== null) {
            return $this->shouldRetryOnException($exception);
        }

        if ($response !== null) {
            return $this->shouldRetryOnResponse($response);
        }

        return false;
    }

    protected function shouldRetryOnException(Throwable $exception): bool
    {
        if ($exception instanceof ChipValidationException) {
            return false;
        }

        if ($exception instanceof ChipApiException) {
            return $exception->getStatusCode() >= 500;
        }

        return $exception instanceof ConnectionException;
    }

    protected function shouldRetryOnResponse(Response $response): bool
    {
        return $response->serverError();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function logRequest(string $method, string $url, array $data): void
    {
        if (! $this->shouldLogRequests()) {
            return;
        }

        Log::channel($this->logChannel())
            ->info($this->requestLogMessage(), [
                'method' => $method,
                'url' => $url,
                'data' => $this->maskSensitiveData($data),
            ]);
    }

    protected function logResponse(?Response $response): void
    {
        if (! $response || ! $this->shouldLogResponses()) {
            return;
        }

        Log::channel($this->logChannel())
            ->info($this->responseLogMessage(), [
                'status' => $response->status(),
                'data' => $this->maskSensitiveData($response->json() ?? []),
            ]);
    }

    protected function shouldLogRequests(): bool
    {
        return $this->loggingEnabled() && (bool) config('chip.logging.log_requests', false);
    }

    protected function shouldLogResponses(): bool
    {
        return $this->loggingEnabled() && (bool) config('chip.logging.log_responses', false);
    }

    protected function loggingEnabled(): bool
    {
        return (bool) config('chip.logging.enabled', false);
    }

    protected function requestLogMessage(): string
    {
        return 'CHIP API Request';
    }

    protected function responseLogMessage(): string
    {
        return 'CHIP API Response';
    }

    protected function logChannel(): string
    {
        return config('chip.logging.channel', 'stack');
    }

    /**
     * Recursively mask sensitive data in nested arrays.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveData(array $data, int $depth = 0): array
    {
        if (! config('chip.logging.mask_sensitive_data', true)) {
            return $data;
        }

        $fields = $this->sensitiveFields();

        if ($fields === []) {
            return $data;
        }

        // Prevent infinite recursion on deeply nested structures
        if ($depth > 10) {
            return $data;
        }

        $masked = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $fields, true)) {
                $masked[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value, $depth + 1);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Fields to mask in logs. Override in subclasses to add more.
     *
     * @return array<int, string>
     */
    protected function sensitiveFields(): array
    {
        /** @var array<int, string> $configuredFields */
        $configuredFields = config('chip.logging.sensitive_fields', []);

        return array_unique(array_merge(
            $this->defaultSensitiveFields(),
            $configuredFields
        ));
    }

    /**
     * Default sensitive fields for PII protection.
     *
     * @return array<int, string>
     */
    protected function defaultSensitiveFields(): array
    {
        return [
            // Authentication
            'api_key', 'secret', 'password', 'token', 'authorization',
            // Payment card data
            'card_number', 'cvv', 'cvc', 'expiry', 'card_mask',
            // PII - personal
            'email', 'phone', 'mobile', 'full_name', 'first_name', 'last_name',
            // PII - address
            'street_address', 'address', 'postal_code', 'zip_code',
            // PII - financial
            'account_number', 'bank_account', 'iban', 'routing_number',
            // PII - identity
            'ic_number', 'nric', 'passport', 'tax_id', 'registration_number',
        ];
    }

    protected function handleException(Exception $exception): never
    {
        if ($this->loggingEnabled()) {
            Log::channel($this->logChannel())
                ->error('CHIP API Request Failed', [
                    'error' => $exception->getMessage(),
                    'exception_class' => $exception::class,
                    'code' => $exception->getCode(),
                ]);
        }

        if ($exception instanceof ChipApiException) {
            throw $exception;
        }

        throw new ChipApiException('API request failed: ' . $exception->getMessage(), 0, [], $exception);
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => config('chip.defaults.creator_agent', 'AIArmada/Chip Laravel Package'),
        ];
    }

    /**
     * This method is only used for test setup - not actual API calls
     */
    /**
     * @param  array<string, string>  $headers
     */
    protected function httpWithHeaders(array $headers): PendingRequest
    {
        return Http::withHeaders($headers);
    }
}
