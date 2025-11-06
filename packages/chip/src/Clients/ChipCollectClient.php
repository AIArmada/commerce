<?php

declare(strict_types=1);

namespace AIArmada\Chip\Clients;

use AIArmada\Chip\Clients\Http\BaseHttpClient;
use AIArmada\Chip\Exceptions\ChipApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public function get(string $endpoint): array|string
    {
        if ($endpoint === 'public_key/' || $endpoint === '/public_key/') {
            $url = $this->buildUrl($endpoint);
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => config('chip.defaults.creator_agent', 'AIArmada/Chip Laravel Package'),
            ])->timeout($this->timeout)->get($url);

            if ($response->failed()) {
                $this->handleFailedResponse($response);
            }

            return $response->body(); // PEM string
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
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
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
        $responseData = $response->json() ?? [];
        $message = $responseData['message'] ?? $responseData['error'] ?? "API request failed with status {$statusCode}";

        // Log the full error response for debugging
        Log::error('CHIP API Error Response', [
            'status' => $statusCode,
            'message' => $message,
            'response_data' => $responseData,
            'response_body' => $response->body(),
        ]);

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
