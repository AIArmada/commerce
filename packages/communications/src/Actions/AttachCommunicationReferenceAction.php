<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Database\Eloquent\Model;

final class AttachCommunicationReferenceAction
{
    public function __construct(
        private readonly EventReferenceNormalizer $normalizer,
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $communicationId,
        Model | CommunicationContextData | array | string $referenceType,
        string | int | null $referenceId = null,
        ?string $role = null,
        ?array $metadata = null,
    ): CommunicationReference {
        $communication = Communication::query()->findOrFail($communicationId);
        $referenceValues = $this->normalizer->normalize($referenceType, $referenceId);

        $reference = $communication->references()
            ->where('reference_type', $referenceValues['type'])
            ->where('reference_id', $referenceValues['id'])
            ->where('role', $role)
            ->first();

        if ($reference instanceof CommunicationReference) {
            if ($metadata !== null) {
                $reference->metadata = $this->redactor->redact(array_merge($reference->metadata ?? [], $metadata));
                $reference->save();
            }

            return $reference;
        }

        $reference = new CommunicationReference;
        $reference->communication_id = $communication->id;
        $reference->reference_type = $referenceValues['type'];
        $reference->reference_id = $referenceValues['id'];
        $reference->role = $role;
        $reference->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;
        $reference->save();

        return $reference;
    }
}
