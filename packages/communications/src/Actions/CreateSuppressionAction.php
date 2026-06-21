<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Enums\SuppressionReason;
use AIArmada\Communications\Events\SuppressionCreated;
use AIArmada\Communications\Models\CommunicationSuppression;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class CreateSuppressionAction
{
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
        $suppression = new CommunicationSuppression;
        $suppression->recipient_type = $recipientType;
        $suppression->recipient_id = $recipientId;
        $suppression->destination_hash = $destinationHash;
        $suppression->channel = $channel;
        $suppression->category = $category;
        $suppression->reason = $reason;
        $suppression->source = $source;
        $suppression->starts_at = $startsAt !== null ? CarbonImmutable::parse($startsAt) : CarbonImmutable::now();
        $suppression->expires_at = $expiresAt !== null ? CarbonImmutable::parse($expiresAt) : null;
        $suppression->created_by_type = $createdByType;
        $suppression->created_by_id = $createdById;
        $suppression->metadata = $metadata;
        $suppression->save();

        Event::dispatch(new SuppressionCreated(
            suppressionId: $suppression->id,
            reason: $reason->value,
            destinationHash: $destinationHash,
        ));

        return $suppression;
    }
}
