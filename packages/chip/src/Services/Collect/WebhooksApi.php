<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

final class WebhooksApi extends CollectApi
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return $this->attempt(
            fn () => $this->client->post('webhooks/', $data),
            'Failed to create CHIP webhook',
            ['data' => $data]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $webhookId): array
    {
        return $this->attempt(
            fn () => $this->client->get("webhooks/{$webhookId}/"),
            'Failed to get CHIP webhook',
            ['webhook_id' => $webhookId]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $webhookId, array $data): array
    {
        return $this->attempt(
            fn () => $this->client->put("webhooks/{$webhookId}/", $data),
            'Failed to update CHIP webhook',
            ['webhook_id' => $webhookId, 'data' => $data]
        );
    }

    public function delete(string $webhookId): void
    {
        $this->attempt(
            fn () => $this->client->delete("webhooks/{$webhookId}/"),
            'Failed to delete CHIP webhook',
            ['webhook_id' => $webhookId]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'webhooks/'.($queryString ? '?'.$queryString : '');

        return $this->attempt(
            fn () => $this->client->get($endpoint),
            'Failed to list CHIP webhooks',
            ['filters' => $filters]
        );
    }
}
