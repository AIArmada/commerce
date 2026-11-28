<?php

declare(strict_types=1);

namespace AIArmada\Communications\Data;

use BackedEnum;
use InvalidArgumentException;

final readonly class CommunicationContextData
{
    public function __construct(
        public string $direction = 'outbound',
        public string $category = 'transactional',
        public string $priority = 'normal',
        public ?string $purpose = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $senderType = null,
        public ?string $senderId = null,
        public ?string $batchId = null,
        public ?string $threadId = null,
        public ?string $parentId = null,
        public ?string $locale = null,
        public ?string $timezone = null,
        public ?string $idempotencyKey = null,
        public ?string $scheduledAt = null,
        public ?string $expiresAt = null,
        public array $metadata = [],
    ) {}

    /** @param array<string, mixed> $values */
    public static function from(array $values): self
    {
        return new self(
            direction: self::enumValue($values['direction'] ?? 'outbound'),
            category: self::enumValue($values['category'] ?? 'transactional'),
            priority: self::enumValue($values['priority'] ?? 'normal'),
            purpose: $values['purpose'] ?? null,
            subjectType: $values['subjectType'] ?? null,
            subjectId: $values['subjectId'] ?? null,
            senderType: $values['senderType'] ?? null,
            senderId: $values['senderId'] ?? null,
            batchId: $values['batchId'] ?? null,
            threadId: $values['threadId'] ?? null,
            parentId: $values['parentId'] ?? null,
            locale: $values['locale'] ?? null,
            timezone: $values['timezone'] ?? null,
            idempotencyKey: $values['idempotencyKey'] ?? null,
            scheduledAt: $values['scheduledAt'] ?? null,
            expiresAt: $values['expiresAt'] ?? null,
            metadata: $values['metadata'] ?? [],
        );
    }

    private static function enumValue(mixed $value): string
    {
        $value = $value instanceof BackedEnum ? $value->value : $value;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException('Communication context values must be non-empty strings.');
        }

        return $value;
    }
}
