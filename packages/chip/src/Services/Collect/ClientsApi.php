<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

use AIArmada\Chip\DataObjects\Client;

final class ClientsApi extends CollectApi
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client
    {
        $response = $this->attempt(
            fn () => $this->client->post('clients/', $data),
            'Failed to create CHIP client',
            ['data' => $data]
        );

        return Client::fromArray($response);
    }

    public function find(string $clientId): Client
    {
        $response = $this->attempt(
            fn () => $this->client->get("clients/{$clientId}/"),
            'Failed to retrieve CHIP client',
            ['client_id' => $clientId]
        );

        return Client::fromArray($response);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'clients/'.($queryString ? '?'.$queryString : '');

        return $this->attempt(
            fn () => $this->client->get($endpoint),
            'Failed to list CHIP clients',
            ['filters' => $filters]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $clientId, array $data): Client
    {
        $response = $this->attempt(
            fn () => $this->client->put("clients/{$clientId}/", $data),
            'Failed to update CHIP client',
            ['client_id' => $clientId, 'data' => $data]
        );

        return Client::fromArray($response);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function partialUpdate(string $clientId, array $data): Client
    {
        $response = $this->attempt(
            fn () => $this->client->patch("clients/{$clientId}/", $data),
            'Failed to partially update CHIP client',
            ['client_id' => $clientId, 'data' => $data]
        );

        return Client::fromArray($response);
    }

    public function delete(string $clientId): void
    {
        $this->attempt(
            fn () => $this->client->delete("clients/{$clientId}/"),
            'Failed to delete CHIP client',
            ['client_id' => $clientId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function recurringTokens(string $clientId): array
    {
        return $this->attempt(
            fn () => $this->client->get("clients/{$clientId}/recurring_tokens/"),
            'Failed to list CHIP client recurring tokens',
            ['client_id' => $clientId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function recurringToken(string $clientId, string $tokenId): array
    {
        return $this->attempt(
            fn () => $this->client->get("clients/{$clientId}/recurring_tokens/{$tokenId}/"),
            'Failed to retrieve CHIP client recurring token',
            ['client_id' => $clientId, 'token_id' => $tokenId]
        );
    }

    public function deleteRecurringToken(string $clientId, string $tokenId): void
    {
        $this->attempt(
            fn () => $this->client->delete("clients/{$clientId}/recurring_tokens/{$tokenId}/"),
            'Failed to delete CHIP client recurring token',
            ['client_id' => $clientId, 'token_id' => $tokenId]
        );
    }
}
