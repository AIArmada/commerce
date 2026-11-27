<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\CommerceSupport\Data\RecordReference;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Enriched webhook payload with local context.
 */
final readonly class EnrichedWebhookPayload
{
    public function __construct(
        public string $event,
        /** @var array<string, mixed> */
        public array $rawPayload,
        public ?string $localPurchaseId = null,
        public ?RecordReference $owner = null,
        public ?CarbonInterface $receivedAt = null,
        public ?CarbonInterface $eventTimestamp = null,
        public ?string $purchaseId = null,
        public ?string $clientId = null,
    ) {}

    /**
     * Create from raw webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(string $event, array $payload, ?string $localPurchaseId = null, ?RecordReference $owner = null): self
    {
        $purchaseId = data_get($payload, 'related_to.type') === 'purchase'
            ? data_get($payload, 'related_to.id')
            : (($payload['type'] ?? null) === 'purchase' ? ($payload['id'] ?? null) : null);
        $purchaseId = is_string($purchaseId) || is_int($purchaseId) ? (string) $purchaseId : null;
        $clientId = $payload['client_id'] ?? (is_array($payload['client'] ?? null) ? ($payload['client']['id'] ?? null) : null);
        $clientId = is_string($clientId) || is_int($clientId) ? (string) $clientId : null;

        $eventTimestamp = self::resolveEventTimestamp($payload);

        return new self(
            event: $event,
            rawPayload: $payload,
            localPurchaseId: $localPurchaseId,
            owner: $owner,
            receivedAt: CarbonImmutable::now(),
            eventTimestamp: $eventTimestamp,
            purchaseId: $purchaseId,
            clientId: $clientId,
        );
    }

    public function hasLocalPurchase(): bool
    {
        return $this->localPurchaseId !== null;
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
    private static function resolveEventTimestamp(array $payload): ?CarbonInterface
    {
        $candidate = $payload['created_on'] ?? null;

        if (is_int($candidate) || is_float($candidate) || (is_string($candidate) && is_numeric($candidate))) {
            return CarbonImmutable::createFromTimestampUTC((int) $candidate);
        }

        if (is_string($candidate) && $candidate !== '') {
            try {
                return CarbonImmutable::parse($candidate);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
