<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Queries;

use AIArmada\Communications\Features\Aggregate\Api\DeliveryReferenceValidator;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationContent;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationRecipient;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class ValidateDeliveryReferences implements DeliveryReferenceValidator
{
    public function validate(string $communicationId, array $recipientIds, array $contentIds): void
    {
        $existingRecipients = $recipientIds === [] ? [] : CommunicationRecipient::query()
            ->where('communication_id', $communicationId)
            ->whereKey($recipientIds)
            ->pluck('id')
            ->all();

        foreach ($recipientIds as $id) {
            if (! in_array($id, $existingRecipients, true)) {
                throw (new ModelNotFoundException)->setModel(CommunicationRecipient::class, [$id]);
            }
        }

        $existingContents = $contentIds === [] ? [] : CommunicationContent::query()
            ->where('communication_id', $communicationId)
            ->whereKey($contentIds)
            ->pluck('id')
            ->all();

        foreach ($contentIds as $id) {
            if (! in_array($id, $existingContents, true)) {
                throw (new ModelNotFoundException)->setModel(CommunicationContent::class, [$id]);
            }
        }
    }
}
