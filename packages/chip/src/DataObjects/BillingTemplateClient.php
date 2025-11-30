<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

/**
 * CHIP BillingTemplateClient data object.
 *
 * Represents a client subscribed to a billing template (subscription).
 */
class BillingTemplateClient
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $created_on,
        public readonly int $updated_on,
        public readonly string $status,
        public readonly string $billing_template_id,
        public readonly string $client_id,
        public readonly ?string $recurring_token,
        public readonly ?int $next_billing_on,
        public readonly ?int $last_billing_on,
        public readonly ?string $company_id,
        public readonly bool $is_test,
        /** @var array<string, mixed> */
        public readonly array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            type: $data['type'] ?? 'billing_template_client',
            created_on: $data['created_on'] ?? time(),
            updated_on: $data['updated_on'] ?? time(),
            status: $data['status'] ?? 'active',
            billing_template_id: $data['billing_template_id'] ?? '',
            client_id: $data['client_id'] ?? '',
            recurring_token: $data['recurring_token'] ?? null,
            next_billing_on: $data['next_billing_on'] ?? null,
            last_billing_on: $data['last_billing_on'] ?? null,
            company_id: $data['company_id'] ?? null,
            is_test: $data['is_test'] ?? true,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function getCreatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->created_on);
    }

    public function getUpdatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->updated_on);
    }

    public function getNextBillingAt(): ?Carbon
    {
        return $this->next_billing_on ? Carbon::createFromTimestamp($this->next_billing_on) : null;
    }

    public function getLastBillingAt(): ?Carbon
    {
        return $this->last_billing_on ? Carbon::createFromTimestamp($this->last_billing_on) : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'subscription_paused';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['subscription_paused', 'cancelled', 'canceled']);
    }

    public function hasRecurringToken(): bool
    {
        return $this->recurring_token !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
            'status' => $this->status,
            'billing_template_id' => $this->billing_template_id,
            'client_id' => $this->client_id,
            'recurring_token' => $this->recurring_token,
            'next_billing_on' => $this->next_billing_on,
            'last_billing_on' => $this->last_billing_on,
            'company_id' => $this->company_id,
            'is_test' => $this->is_test,
            'metadata' => $this->metadata,
        ];
    }
}
