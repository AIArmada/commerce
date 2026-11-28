<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

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

        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        $referenceValues = $this->normalizer->normalize($referenceType, $referenceId);

        return $communication->getConnection()->transaction(function () use ($communication, $referenceValues, $role, $metadata): CommunicationReference {
            $reference = $this->findReference($communication, $referenceValues['type'], $referenceValues['id'], $role);

            if ($reference instanceof CommunicationReference) {
                return $this->applyMetadata($reference, $metadata);
            }

            $reference = new CommunicationReference;
            $reference->communication_id = $communication->id;
            $reference->reference_type = $referenceValues['type'];
            $reference->reference_id = $referenceValues['id'];
            $reference->role = $role;
            $reference->metadata = $metadata !== null ? $this->redactor->redact($metadata) : null;

            try {
                $reference->getConnection()->transaction(static fn (): bool => $reference->save());
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $existing = $this->findReference($communication, $referenceValues['type'], $referenceValues['id'], $role);

                if (! $existing instanceof CommunicationReference) {
                    throw $exception;
                }

                return $this->applyMetadata($existing, $metadata);
            }

            return $reference;
        });
    }

    private function findReference(
        Communication $communication,
        string $referenceType,
        string $referenceId,
        ?string $role,
    ): ?CommunicationReference {
        return $communication->references()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('role', $role)
            ->lockForUpdate()
            ->first();
    }

    private function applyMetadata(CommunicationReference $reference, ?array $metadata): CommunicationReference
    {
        if ($metadata === null) {
            return $reference;
        }

        $reference->metadata = $this->redactor->redact(array_merge($reference->metadata ?? [], $metadata));
        $reference->save();

        return $reference;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
