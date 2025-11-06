<?php

declare(strict_types=1);

namespace AIArmada\Chip\Clients;

use AIArmada\Chip\Clients\Http\BaseHttpClient;
use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\ChipValidationException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ChipSendClient extends BaseHttpClient
{
    /**
     * @param  array<string, mixed>  $retryConfig
     */
    public function __construct(
        protected string $apiKey,
        protected string $apiSecret,
        protected string $environment = 'sandbox',
        protected string $baseUrl = '',
        protected int $timeout = 30,
        protected array $retryConfig = []
    ) {
        parent::__construct($timeout, $retryConfig);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
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

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    protected function resolveBaseUrl(): string
    {
        if (! empty($this->baseUrl)) {
            return $this->baseUrl;
        }

        $baseUrls = [
            'sandbox' => 'https://staging-api.chip-in.asia/api',
            'production' => 'https://api.chip-in.asia/api',
        ];

        return $baseUrls[$this->environment] ?? $baseUrls['sandbox'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function sendRequest(string $method, string $url, array $data, array $headers = []): Response
    {
        $epoch = time();
        $checksum = $this->generateChecksum($epoch);

        $defaultHeaders = [
            'Authorization' => "Bearer {$this->apiKey}",
            'epoch' => (string) $epoch,
            'checksum' => $checksum,
        ];

        return Http::withHeaders(array_merge($this->defaultHeaders(), $defaultHeaders, $headers))
            ->timeout($this->timeout)
            ->send($method, $url, [
                'json' => $data,
            ]);
    }

    protected function generateChecksum(int $epoch): string
    {
        return hash_hmac('sha256', (string) $epoch, $this->apiSecret);
    }

    protected function handleFailedResponse(Response $response): never
    {
        $statusCode = $response->status();
        $responseData = $response->json() ?? [];
        $message = $responseData['message'] ?? $responseData['error'] ?? "API request failed with status {$statusCode}";

        if ($statusCode === 400) {
            throw new ChipValidationException($message, $responseData, $statusCode);
        }

        throw new ChipApiException($message, $statusCode, $responseData);
    }

    /**
     * @return array<int, string>
     */
    protected function sensitiveFields(): array
    {
        return array_merge(parent::sensitiveFields(), [
            'api_secret',
            'account_number',
            'bank_account',
        ]);
    }

    protected function requestLogMessage(): string
    {
        return 'CHIP Send API Request';
    }

    protected function responseLogMessage(): string
    {
        return 'CHIP Send API Response';
    }
}
