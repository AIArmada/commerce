<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * Enriched webhook payload with local context.
 */
final class EnrichedWebhookPayload extends Data
{
    public function __construct(
        public readonly string $event,
        /** @var array<string, mixed> */
        public readonly array $rawPayload,
        public readonly ?Purchase $localPurchase = null,
        public readonly ?Model $owner = null,
        public readonly ?CarbonInterface $receivedAt = null,
        public readonly ?CarbonInterface $eventTimestamp = null,
        public readonly ?string $purchaseId = null,
        public readonly ?string $clientId = null,
    ) {}

    /**
     * Create from raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(string $event, array $payload): self
    {
        $purchaseId = $payload['id'] ?? $payload['data']['id'] ?? null;
        $purchaseId = is_string($purchaseId) || is_int($purchaseId) ? (string) $purchaseId : null;
        $clientId = $payload['client_id'] ?? $payload['client']['id'] ?? $payload['data']['client_id'] ?? null;
        $clientId = is_string($clientId) || is_int($clientId) ? (string) $clientId : null;

        $localPurchase = null;
        $owner = self::resolveOwnerFromPayload($payload);

        if ($purchaseId) {
            $query = Purchase::query();

            if ($owner !== null && method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner($owner);
            }

            $localPurchase = $query->whereKey($purchaseId)->first();
            $owner ??= $localPurchase?->owner;
        }

        $eventTimestamp = self::resolveEventTimestamp($payload);

        return new self(
            event: $event,
            rawPayload: $payload,
            localPurchase: $localPurchase,
            owner: $owner,
            receivedAt: CarbonImmutable::now(),
            eventTimestamp: $eventTimestamp,
            purchaseId: $purchaseId,
            clientId: $clientId,
        );
    }

    public function hasLocalPurchase(): bool
    {
        return $this->localPurchase !== null;
    }

    public function hasOwner(): bool
    {
        return $this->owner !== null;
    }

    /**
     * Get a value from the raw payload using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->rawPayload, $key, $default);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveOwnerFromPayload(array $payload): ?Model
    {
        $ownerType = $payload['__owner_type'] ?? null;
        $ownerId = $payload['__owner_id'] ?? null;

        if (! is_string($ownerType) || (! is_string($ownerId) && ! is_int($ownerId))) {
            return null;
        }

        return OwnerContext::fromTypeAndId($ownerType, $ownerId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveEventTimestamp(array $payload): ?CarbonInterface
    {
        $candidate = $payload['created'] ?? $payload['created_on'] ?? null;

        if (is_int($candidate) || is_float($candidate) || (is_string($candidate) && is_numeric($candidate))) {
            return CarbonImmutable::createFromTimestampUTC((int) $candidate);
        }

        if (is_string($candidate) && $candidate !== '') {
            return CarbonImmutable::parse($candidate);
        }

        return null;
    }
}
