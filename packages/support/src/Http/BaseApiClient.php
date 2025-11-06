<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Http;

use AIArmada\CommerceSupport\Exceptions\CommerceApiException;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Base API Client for external integrations.
 *
 * Provides standardized HTTP client functionality using Laravel HTTP client
 * with retry logic, authentication, request/response logging, and error handling.
 *
 * This class replaces the older Guzzle-based BaseHttpClient and should be used
 * for all new API integrations (payment gateways, shipping providers, etc.).
 */
abstract class BaseApiClient
{
    /**
     * Maximum number of retry attempts for failed requests.
     */
    protected int $maxRetries = 3;

    /**
     * Initial delay in milliseconds before retry.
     */
    protected int $retryDelay = 1000;

    /**
     * Request timeout in seconds.
     */
    protected int $timeout = 30;

    /**
     * Connection timeout in seconds.
     */
    protected int $connectTimeout = 10;

    /**
     * Whether to log requests and responses.
     */
    protected bool $enableLogging = true;

    /**
     * Log channel to use for API calls.
     */
    protected ?string $logChannel = null;

    /**
     * Get the base URL for the API.
     */
    abstract protected function resolveBaseUrl(): string;

    /**
     * Authenticate the request (add headers, tokens, etc.).
     */
    abstract protected function authenticateRequest(PendingRequest $request): PendingRequest;

    /**
     * Handle a failed API response.
     *
     * @throws CommerceApiException
     */
    abstract protected function handleFailedResponse(Response $response): never;

    /**
     * Make an API request.
     *
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param  string  $endpoint  API endpoint path
     * @param  array<string, mixed>  $data  Request data (body or query params)
     * @return array<string, mixed> Response data
     *
     * @throws CommerceApiException
     */
    final public function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->buildUrl($endpoint);
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $response = $this->sendRequest($method, $url, $data);

                // Check if we should retry on this response
                if ($response->failed() && $attempt < $this->maxRetries && $this->shouldRetry(null, $response)) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                // If response failed and we're not retrying, handle the error
                if ($response->failed()) {
                    $this->handleFailedResponse($response);
                }

                // Success! Return the response data
                return $response->json() ?? [];
            } catch (RequestException $exception) {
                $lastException = $exception;

                // If we have retries left and should retry, wait and continue
                if ($attempt < $this->maxRetries && $this->shouldRetry($exception, null)) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                // No more retries, handle the error
                $this->handleRequestException($exception);
            } catch (Exception $exception) {
                $lastException = $exception;

                // For other exceptions, only retry on connection issues
                if ($attempt < $this->maxRetries && $this->shouldRetry($exception, null)) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                throw $exception;
            }
        }

        // If we get here, we've exhausted retries
        throw $lastException ?? new CommerceApiException(
            'Maximum retry attempts exceeded',
            statusCode: 0,
            endpoint: $endpoint
        );
    }

    /**
     * Configure retry behavior.
     */
    final public function withRetry(int $maxRetries, int $retryDelay): static
    {
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;

        return $this;
    }

    /**
     * Configure timeout settings.
     */
    final public function withTimeout(int $timeout, ?int $connectTimeout = null): static
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout ?? $timeout;

        return $this;
    }

    /**
     * Configure logging.
     */
    final public function withLogging(bool $enable = true, ?string $channel = null): static
    {
        $this->enableLogging = $enable;
        $this->logChannel = $channel;

        return $this;
    }

    /**
     * Send the actual HTTP request.
     *
     * @param  array<string, mixed>  $data
     */
    protected function sendRequest(string $method, string $url, array $data): Response
    {
        $request = $this->prepareRequest();

        $this->logRequest($method, $url, $data);

        $response = match (mb_strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $this->logResponse($response);

        return $response;
    }

    /**
     * Prepare the HTTP request with authentication and configuration.
     */
    protected function prepareRequest(): PendingRequest
    {
        $request = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->acceptJson();

        return $this->authenticateRequest($request);
    }

    /**
     * Build the full URL from base URL and endpoint.
     */
    protected function buildUrl(string $endpoint): string
    {
        $baseUrl = mb_rtrim($this->resolveBaseUrl(), '/');
        $endpoint = mb_ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    /**
     * Determine if the request should be retried.
     *
     * Override this method to customize retry logic.
     */
    protected function shouldRetry(?Throwable $exception, ?Response $response): bool
    {
        // Retry on connection exceptions
        if ($exception instanceof RequestException) {
            return true;
        }

        // Retry on 5xx server errors
        if ($response && $response->serverError()) {
            return true;
        }

        // Retry on specific 4xx errors (e.g., 429 Rate Limit)
        if ($response && $response->status() === 429) {
            return true;
        }

        return false;
    }

    /**
     * Wait before retrying with exponential backoff.
     */
    protected function waitBeforeRetry(int $attempt): void
    {
        $delay = $this->retryDelay * pow(2, $attempt - 1); // Exponential backoff
        usleep($delay * 1000); // Convert to microseconds
    }

    /**
     * Handle a request exception.
     *
     * @throws CommerceApiException
     */
    protected function handleRequestException(RequestException $exception): never
    {
        throw new CommerceApiException(
            message: $exception->getMessage(),
            statusCode: $exception->response->status(),
            errorData: $exception->response->json() ?? [],
            apiResponse: $exception->response->body(),
            previous: $exception
        );
    }

    /**
     * Log the API request.
     *
     * @param  array<string, mixed>  $data
     */
    protected function logRequest(string $method, string $url, array $data): void
    {
        if (! $this->enableLogging) {
            return;
        }

        $logger = $this->logChannel ? Log::channel($this->logChannel) : Log::channel();

        $logger->debug('API Request', [
            'method' => $method,
            'url' => $url,
            'data' => $this->sanitizeLogData($data),
        ]);
    }

    /**
     * Log the API response.
     */
    protected function logResponse(Response $response): void
    {
        if (! $this->enableLogging) {
            return;
        }

        $logger = $this->logChannel ? Log::channel($this->logChannel) : Log::channel();

        $level = $response->successful() ? 'debug' : 'error';

        $logger->log($level, 'API Response', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $this->sanitizeLogData($response->json() ?? []),
        ]);
    }

    /**
     * Sanitize sensitive data before logging.
     *
     * Override this method to mask API keys, tokens, passwords, etc.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeLogData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'token',
            'api_key',
            'secret',
            'authorization',
            'card_number',
            'cvv',
            'pin',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }
}
