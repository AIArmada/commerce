<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\WebhookDelivery\Api;

interface WebhookRegistrationOperations
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * @return array<string, mixed>
     */
    public function find(string $webhookId): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $webhookId, array $data): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function partialUpdate(string $webhookId, array $data): array;

    public function delete(string $webhookId): void;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters = []): array;
}
