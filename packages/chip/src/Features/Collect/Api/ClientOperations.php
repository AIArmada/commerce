<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Collect\Api;

use AIArmada\Chip\Data\ClientData;

interface ClientOperations
{
    /** @param array<string, mixed> $data */
    public function create(array $data): ClientData;

    public function find(string $clientId): ClientData;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array;

    /** @param array<string, mixed> $data */
    public function update(string $clientId, array $data): ClientData;

    /** @param array<string, mixed> $data */
    public function partialUpdate(string $clientId, array $data): ClientData;

    public function delete(string $clientId): void;

    /** @return array<string, mixed> */
    public function recurringTokens(string $clientId): array;

    /** @return array<string, mixed> */
    public function recurringToken(string $clientId, string $tokenId): array;

    public function deleteRecurringToken(string $clientId, string $tokenId): void;
}
