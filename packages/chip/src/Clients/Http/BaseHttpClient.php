<?php

declare(strict_types=1);

namespace AIArmada\Chip\Clients\Http;

use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        return mb_rtrim($this->resolveBaseUrl(), '/').'/'.mb_ltrim($endpoint, '/');
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        if (! config('chip.logging.mask_sensitive_data', true)) {
            return $data;
        }

        $fields = $this->sensitiveFields();

        if ($fields === []) {
            return $data;
        }

        $masked = $data;

        foreach ($fields as $field) {
            if (array_key_exists($field, $masked)) {
                $masked[$field] = '***MASKED***';
            }
        }

        return $masked;
    }

    /**
     * @return array<int, string>
     */
    protected function sensitiveFields(): array
    {
        return ['api_key', 'secret', 'password', 'token', 'card_number', 'cvv'];
    }

    protected function handleException(Exception $exception): never
    {
        if ($this->loggingEnabled()) {
            Log::channel($this->logChannel())
                ->error('CHIP API Request Failed', [
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);
        }

        if ($exception instanceof ChipApiException) {
            throw $exception;
        }

        throw new ChipApiException('API request failed: '.$exception->getMessage(), 0, [], $exception);
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
