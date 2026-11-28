<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\CommunicationReferenceWriter;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Features\Aggregate\Models\CommunicationReference;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Support\EventReferenceNormalizer;
use Illuminate\Database\QueryException;

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

            $reference = $this->findReference($communication, $referenceValues['type'], $referenceValues['id'], $role);

            if ($reference instanceof CommunicationReference) {
                return (string) $this->applyMetadata($reference, $metadata)->getKey();
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

                return (string) $this->applyMetadata($existing, $metadata)->getKey();
            }

            return (string) $reference->getKey();
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
