<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\CommunicationReferenceWriter;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationReference;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Support\EventReferenceNormalizer;

final class AttachCommunicationReferenceAction implements CommunicationReferenceWriter
{
    public function __construct(
        private readonly EventReferenceNormalizer $normalizer,
        private readonly PayloadRedactor $redactor,
    ) {}

    public function handle(
        string $communicationId,
        string $referenceType,
        string $referenceId,
        ?string $role = null,
        ?array $metadata = null,
    ): string {
        $referenceValues = $this->normalizer->normalize($referenceType, $referenceId);

        return (new Communication)->getConnection()->transaction(function () use ($communicationId, $referenceValues, $role, $metadata): string {
            $communication = Communication::query()->whereKey($communicationId)->lockForUpdate()->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

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

                return (string) $reference->getKey();
            }

            $reference = new CommunicationReference;
            $reference->communication_id = $communication->id;
            $reference->reference_type = $referenceValues['type'];
            $reference->reference_id = $referenceValues['id'];
            $reference->role = $role;
            $reference->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;
            $reference->save();

            return (string) $reference->getKey();
        });
    }
}
