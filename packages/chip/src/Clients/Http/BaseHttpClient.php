<?php

declare(strict_types=1);

namespace AIArmada\Chip\Clients\Http;

use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipRateLimitException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use DateTimeImmutable;
use DateTimeZone;
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
        $response = $this->performRequest($method, $endpoint, $data, $headers);

        return $response->json() ?? [];
    }

    /**
     * Perform an HTTP request through the shared pipeline (rate limiting,
     * retries, logging) and return the raw response body.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    final public function requestRaw(string $method, string $endpoint, array $data = [], array $headers = []): string
    {
        return $this->performRequest($method, $endpoint, $data, $headers)->body();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function performRequest(string $method, string $endpoint, array $data = [], array $headers = []): Response
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

                    if ($response->failed() && $attempt < $attempts && $this->shouldRetry($method, null, $response)) {
                        usleep($delayMilliseconds * 1000);

                        continue;
                    }

                    if ($response->failed()) {
                        $this->handleFailedResponse($response);
                    }

                    break;
                } catch (Exception $exception) {
                    if ($attempt >= $attempts || ! $this->shouldRetry($method, $exception, null)) {
                        throw $exception;
                    }

                    usleep($delayMilliseconds * 1000);
                }
            }

            $this->logResponse($response);

            if (! $response instanceof Response) {
                throw new ChipApiException('API request failed: no response was received.');
            }

            return $response;
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

            if ($this->loggingEnabled()) {
                Log::channel($this->logChannel())->warning('CHIP API rate limited', [
                    'retry_after' => $retryAfter,
                    'source' => 'local',
                ]);
            }

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
        $key = 'chip_api:' . static::class;

        if (! (bool) config('chip.owner.enabled', false)) {
            return $key;
        }

        return $key . ':' . OwnerScopeKey::forOwner(OwnerContext::resolve());
    }

    protected function rateLimitMaxAttempts(): int
    {
        return (int) config('chip.http.rate_limit.max_attempts', 60);
    }

    protected function rateLimitDecaySeconds(): int
    {
        return (int) config('chip.http.rate_limit.decay_seconds', 60);
    }

    protected function shouldRetry(string $method, ?Throwable $exception, ?Response $response): bool
    {
        if (! $this->isRetryableMethod($method)) {
            return false;
        }

        if ($exception !== null) {
            return $this->shouldRetryOnException($exception);
        }

        if ($response !== null) {
            return $this->shouldRetryOnResponse($response);
        }

        return false;
    }

    /**
     * Mutations carry Idempotency-Key when a key is resolved, but CHIP
     * ignores it (sandbox replay P4: identical body + key created a
     * second purchase, so the local ledger is the only dedupe), so
     * automatic retries stay restricted to methods that do not create
     * or change a remote resource: a connection failure must not
     * repeat a mutation on an assumption.
     */
    protected function isRetryableMethod(string $method): bool
    {
        return in_array(mb_strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
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

        $body = $response->json();

        Log::channel($this->logChannel())
            ->info($this->responseLogMessage(), [
                'status' => $response->status(),
                'data' => $this->maskSensitiveData(is_array($body) ? $body : []),
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
        // Rate limiting is already logged at the response site; a second
        // error-level line here would misreport a warning as a failure.
        if ($exception instanceof ChipRateLimitException) {
            throw $exception;
        }

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
     * Delay-seconds from a server Retry-After header, which may be
     * delay-seconds or an HTTP-date (RFC 9110 section 10.2.3). Past dates
     * clamp to zero; absent or unparseable values default to 60.
     */
    protected function retryAfterSeconds(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');
        $value = is_array($retryAfter) ? ($retryAfter[0] ?? null) : $retryAfter;

        if (! is_string($value)) {
            return 60;
        }

        $trimmed = mb_trim($value);

        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        $timestamp = $this->parseHttpDate($trimmed);

        if ($timestamp === null) {
            return 60;
        }

        return max(0, $timestamp - time());
    }

    /**
     * Strict HTTP-date parse (RFC 9110 section 5.6.7): IMF-fixdate, RFC 850,
     * and ANSI C asctime. Anything else — including relative strings like
     * "tomorrow" that strtotime() would accept — returns null. The weekday
     * token is validated against the date itself: a mismatch returns null
     * instead of silently shifting to the named weekday.
     */
    private function parseHttpDate(string $value): ?int
    {
        $normalized = preg_replace('/\s+/', ' ', $value);

        if (! is_string($normalized)) {
            return null;
        }

        // Two-digit RFC 850 takes its own path: the token must evidence
        // either the rule-resolved century or the tentative one (a sender
        // past the boundary still names the future weekday). Anything else
        // falls through to the single-century shapes below.
        if (preg_match(self::RFC850_TWO_DIGIT_PATTERN, $normalized) === 1) {
            return $this->parseRfc850TwoDigit($normalized);
        }

        $shapes = [
            ['split' => '/^([A-Za-z]+), (.*)$/', 'dateless' => 'd M Y H:i:s \G\M\T', 'day' => 'D'],
            ['split' => '/^([A-Za-z]+), (.*)$/', 'dateless' => 'd-M-Y H:i:s \G\M\T', 'day' => 'l'],
            ['split' => '/^([A-Za-z]+) (.*)$/', 'dateless' => 'M j H:i:s Y', 'day' => 'D'],
        ];

        foreach ($shapes as $shape) {
            if (preg_match($shape['split'], $normalized, $matches) !== 1) {
                continue;
            }

            $dateless = DateTimeImmutable::createFromFormat($shape['dateless'], $matches[2], new DateTimeZone('GMT'));

            if ($dateless === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            if (mb_strtolower($matches[1]) !== mb_strtolower($dateless->format($shape['day']))) {
                continue;
            }

            return $dateless->getTimestamp();
        }

        return null;
    }

    private const string RFC850_TWO_DIGIT_PATTERN = '/^([A-Za-z]+, \d{2}-[A-Za-z]{3}-)(\d{2})( \d{2}:\d{2}:\d{2} GMT)$/';

    /**
     * Two-digit RFC 850: expand the year per RFC 9110 section 5.6.7 (a
     * timestamp more than 50 years out takes the past century; PHP's `y`
     * would use a fixed 1970-2069 window instead), then validate the
     * weekday token against the rule-resolved date or the tentative
     * (current century) one. Anything else is malformed or contradictory.
     * One clock read drives both decisions.
     */
    private function parseRfc850TwoDigit(string $normalized): ?int
    {
        if (preg_match(self::RFC850_TWO_DIGIT_PATTERN, $normalized, $matches) !== 1) {
            return null;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('GMT'));
        $full = intdiv((int) $now->format('Y'), 100) * 100 + (int) $matches[2];
        $tentative = $matches[1] . $full . $matches[3];

        if (preg_match('/^([A-Za-z]+), (.*)$/', $tentative, $tentativeParts) !== 1) {
            return null;
        }

        // Tokenless: a mismatched token must not shift the boundary decision.
        $candidate = DateTimeImmutable::createFromFormat('d-M-Y H:i:s \G\M\T', $tentativeParts[2], new DateTimeZone('GMT'));

        if ($candidate === false) {
            return null;
        }

        // Leap-day inputs move the boundary by a day: 2028-02-29 +50 years
        // normalizes to 2078-03-01 (February has 28 days in 2078). One-day
        // window per leap cycle; if it ever matters, normalize the
        // threshold explicitly instead of raising the 50.
        $expanded = $candidate > $now->modify('+50 years')
            ? $matches[1] . ($full - 100) . $matches[3]
            : $tentative;

        if (preg_match('/^([A-Za-z]+), (.*)$/', $expanded, $parts) !== 1) {
            return null;
        }

        $dateless = DateTimeImmutable::createFromFormat('d-M-Y H:i:s \G\M\T', $parts[2], new DateTimeZone('GMT'));

        if ($dateless === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        $token = mb_strtolower($parts[1]);

        if ($token === mb_strtolower($dateless->format('l'))) {
            return $dateless->getTimestamp();
        }

        $month = (int) $dateless->format('n');
        $day = (int) $dateless->format('j');

        if (! checkdate($month, $day, $full)) {
            return null;
        }

        $tentativeDate = $dateless->setDate($full, $month, $day);

        if ($token === mb_strtolower($tentativeDate->format('l'))) {
            return $dateless->getTimestamp();
        }

        return null;
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
