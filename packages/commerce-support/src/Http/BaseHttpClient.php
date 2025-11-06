<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Http;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Base HTTP Client
 *
 * Abstract base class for HTTP clients with retry logic and logging.
 * Used by chip and jnt packages.
 */
abstract class BaseHttpClient
{
    protected Client $client;

    protected int $timeout;

    /**
     * @var array{attempts: int, delay: int}
     */
    protected array $retryConfig;

    /**
     * Create HTTP client with retry and logging middleware.
     *
     * @param  int  $timeout  Request timeout in seconds
     * @param  array{attempts: int, delay: int}  $retryConfig  Retry configuration
     */
    public function __construct(int $timeout = 30, array $retryConfig = ['attempts' => 3, 'delay' => 1000])
    {
        $this->timeout = $timeout;
        $this->retryConfig = $retryConfig;

        $handlerStack = HandlerStack::create();
        $handlerStack->push($this->retryMiddleware());
        $handlerStack->push($this->loggingMiddleware());

        $this->client = new Client([
            'handler' => $handlerStack,
            'timeout' => $this->timeout,
            'http_errors' => false, // We'll handle errors manually
        ]);
    }

    /**
     * Check if logging is enabled (override in child classes for package-specific config).
     */
    abstract protected function shouldLog(): bool;

    /**
     * Get the Guzzle client instance.
     */
    final public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Create retry middleware that retries on connection errors and 5xx responses.
     */
    protected function retryMiddleware(): callable
    {
        return Middleware::retry(
            function (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?Exception $exception = null
            ): bool {
                // Don't retry if max attempts reached
                if ($retries >= $this->retryConfig['attempts']) {
                    return false;
                }

                // Retry on connection errors
                if ($exception instanceof ConnectException) {
                    $this->logRetry($retries, $request, $exception);

                    return true;
                }

                // Retry on 5xx server errors
                if ($response && $response->getStatusCode() >= 500) {
                    $this->logRetry($retries, $request, null, $response);

                    return true;
                }

                return false;
            },
            function (int $retries): int {
                // Exponential backoff: delay * (2 ^ retries)
                return $this->retryConfig['delay'] * (2 ** ($retries - 1));
            }
        );
    }

    /**
     * Create logging middleware for requests and responses.
     */
    protected function loggingMiddleware(): callable
    {
        return Middleware::tap(
            function (RequestInterface $request): void {
                if ($this->shouldLog()) {
                    Log::debug('HTTP Request', [
                        'method' => $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'headers' => $this->sanitizeHeaders($request->getHeaders()),
                    ]);
                }
            },
            function (RequestInterface $request, $options, ?ResponseInterface $response = null): void {
                if ($this->shouldLog() && $response) {
                    Log::debug('HTTP Response', [
                        'method' => $request->getMethod(),
                        'uri' => (string) $request->getUri(),
                        'status' => $response->getStatusCode(),
                        'body' => $this->sanitizeResponseBody((string) $response->getBody()),
                    ]);
                }
            }
        );
    }

    /**
     * Log retry attempts.
     */
    protected function logRetry(
        int $retries,
        RequestInterface $request,
        ?Exception $exception = null,
        ?ResponseInterface $response = null
    ): void {
        $context = [
            'attempt' => $retries + 1,
            'max_attempts' => $this->retryConfig['attempts'],
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
        ];

        if ($exception) {
            $context['error'] = $exception->getMessage();
        }

        if ($response) {
            $context['status'] = $response->getStatusCode();
        }

        Log::warning('HTTP Request retry', $context);
    }

    /**
     * Sanitize headers to remove sensitive data (override in child classes).
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sanitized = $headers;

        // Remove common sensitive headers
        $sensitiveHeaders = ['authorization', 'api-key', 'x-api-key'];

        foreach ($sensitiveHeaders as $header) {
            if (isset($sanitized[$header])) {
                $sanitized[$header] = '***REDACTED***';
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize response body to remove sensitive data (override in child classes).
     */
    protected function sanitizeResponseBody(string $body): string
    {
        // Base implementation returns truncated body
        return mb_strlen($body) > 1000 ? mb_substr($body, 0, 1000).'...' : $body;
    }
}
