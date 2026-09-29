<?php

declare(strict_types=1);

namespace AIArmada\Chip\Clients;

use AIArmada\Chip\Clients\Http\BaseHttpClient;
use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipRateLimitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class ChipCollectClient extends BaseHttpClient
{
    /**
     * @param  array<string, mixed>  $retryConfig
     */
    public function __construct(
        protected string $apiKey,
        protected string $brandId,
        protected string $baseUrl = 'https://gate.chip-in.asia/api/v1/',
        protected int $timeout = 30,
        protected array $retryConfig = []
    ) {
        parent::__construct($timeout, $retryConfig);
    }

    /**
     * Get data from the API. Returns array for most endpoints, string for public_key/ endpoint.
     *
     * @return array<string, mixed>|string
     */
    public function get(string $endpoint): array | string
    {
        if ($endpoint === 'public_key/' || $endpoint === '/public_key/') {
            $body = $this->requestRaw('GET', $endpoint);

            try {
                $publicKey = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new ChipApiException(
                    'CHIP public key response was not a JSON-encoded string',
                    0,
                    [],
                    $exception,
                );
            }

            if (! is_string($publicKey) || $publicKey === '') {
                throw new ChipApiException(
                    'CHIP public key response did not contain a PEM string',
                );
            }

            return $publicKey;
        }

        return $this->request('GET', $endpoint);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $endpoint, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $endpoint, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->request('PATCH', $endpoint, $data, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $endpoint, array $headers = []): array
    {
        return $this->request('DELETE', $endpoint, [], $headers);
    }

    public function getBrandId(): string
    {
        return $this->brandId;
    }

    protected function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function sendRequest(string $method, string $url, array $data, array $headers = []): Response
    {
        $defaultHeaders = [
            'Authorization' => "Bearer {$this->apiKey}",
        ];

        return Http::withHeaders(array_merge($this->defaultHeaders(), $defaultHeaders, $headers))
            ->timeout($this->timeout)
            ->send($method, $url, [
                'json' => $data,
            ]);
    }

    protected function handleFailedResponse(Response $response): never
    {
        $statusCode = $response->status();

        if ($statusCode === 429) {
            $retryAfter = $this->retryAfterSeconds($response);

            if ($this->loggingEnabled()) {
                $decoded = $response->json();

                Log::channel($this->logChannel())->warning('CHIP API rate limited', [
                    'retry_after' => $retryAfter,
                    'source' => 'server',
                    'response_data' => $this->maskSensitiveData(is_array($decoded) ? $decoded : []),
                ]);
            }

            throw new ChipRateLimitException($retryAfter);
        }

        $responseData = ChipApiException::normalizeErrorData($response->json());
        $allError = ChipApiException::extractAllError($responseData);
        $message = $responseData['message'] ?? $responseData['error'] ?? $allError['message'] ?? "API request failed with status {$statusCode}";

        if ($allError !== null && $allError['code'] !== null && ! isset($responseData['code'])) {
            $responseData['code'] = $allError['code'];
        }

        // Demoted duplicate: handleException logs the authoritative error
        // line (Send relies on it); this keeps the Collect response context
        // at warning so a failure still emits exactly one error line at
        // this layer (service-level attempt() logging is separate).
        if ($this->loggingEnabled()) {
            Log::channel($this->logChannel())->warning('CHIP API Error Response', [
                'status' => $statusCode,
                'message' => $message,
                'response_data' => $this->maskSensitiveData($responseData),
            ]);
        }

        throw new ChipApiException($message, $statusCode, $responseData);
    }

    /**
     * @return array<int, string>
     */
    protected function sensitiveFields(): array
    {
        return array_merge(parent::sensitiveFields(), ['brand_id']);
    }

    protected function requestLogMessage(): string
    {
        return 'CHIP Collect API Request';
    }

    protected function responseLogMessage(): string
    {
        return 'CHIP Collect API Response';
    }
}
