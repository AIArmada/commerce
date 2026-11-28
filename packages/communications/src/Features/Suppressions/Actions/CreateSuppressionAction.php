<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Suppressions\Actions;

use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Enums\SuppressionReason;
use AIArmada\Communications\Events\SuppressionCreated;
use AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class CreateSuppressionAction
{
    public function __construct(
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $destinationHash,
        string $channel,
        SuppressionReason $reason,
        ?string $recipientType = null,
        ?string $recipientId = null,
        ?string $category = null,
        ?string $source = null,
        ?string $startsAt = null,
        ?string $expiresAt = null,
        ?string $createdByType = null,
        ?string $createdById = null,
        ?array $metadata = null,
    ): CommunicationSuppression {
        if (($recipientType === null) !== ($recipientId === null)) {
            throw new InvalidArgumentException('Suppression recipient type and id must both be provided or both be null.');
        }

        $start = $startsAt !== null ? CarbonImmutable::parse($startsAt) : CarbonImmutable::now();
        $expiry = $expiresAt !== null ? CarbonImmutable::parse($expiresAt) : null;

        if ($expiry !== null && $expiry->lte($start)) {
            throw new InvalidArgumentException('Suppression expiry must be later than its start.');
        }

        $suppression = new CommunicationSuppression;
        $suppression->recipient_type = $recipientType;
        $suppression->recipient_id = $recipientId;
        $suppression->destination_hash = $destinationHash;
        $suppression->channel = $channel;
        $suppression->category = $category;
        $suppression->reason = $reason;
        $suppression->source = $source;
        $suppression->starts_at = $start;
        $suppression->expires_at = $expiry;
        $suppression->created_by_type = $createdByType;
        $suppression->created_by_id = $createdById;
        $suppression->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;
        $suppression->save();

        Event::dispatch(new SuppressionCreated(
            suppressionId: $suppression->id,
            reason: $reason->value,
            destinationHash: $destinationHash,
        ));

        return $suppression;
    }
}
