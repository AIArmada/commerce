<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;

final class AttachCommunicationReferenceAction
{
    public function handle(
        string $communicationId,
        string $referenceType,
        string $referenceId,
        ?string $role = null,
        ?array $metadata = null,
    ): CommunicationReference {
        $communication = Communication::query()->findOrFail($communicationId);

        $reference = new CommunicationReference;
        $reference->communication_id = $communication->id;
        $reference->reference_type = $referenceType;
        $reference->reference_id = $referenceId;
        $reference->role = $role;
        $reference->metadata = $metadata;
        $reference->save();

        return $reference;
    }
}
