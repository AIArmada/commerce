<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Models\Reference;
use AIArmada\References\Models\ReferenceContributor;
use InvalidArgumentException;

final readonly class GuardReferenceContributorWrite
{
    public function __construct(
        private LockReferenceForWrite $lock = new LockReferenceForWrite,
        private ValidateContributorIdentity $identity = new ValidateContributorIdentity,
    ) {}

    public function saving(ReferenceContributor $contributor): void
    {
        $reference = $this->lockReference($contributor);

        if ($contributor->exists && $contributor->isDirty(['contributor_type', 'contributor_id', 'role'])) {
            throw new InvalidArgumentException('Replace contributor identities through SyncReferenceContributors.');
        }

        if ($contributor->role === ReferenceContributorRole::Author && $reference->record_kind !== ReferenceRecordKind::Work) {
            throw new InvalidArgumentException('Authors can only be stored on works.');
        }

        $contributor->contributor_type = $this->identity->type($contributor->contributor_type);
        $contributor->contributor_id = $this->identity->id($contributor->contributor_id);
    }

    public function deleting(ReferenceContributor $contributor): void
    {
        $this->lockReference($contributor);
    }

    private function lockReference(ReferenceContributor $contributor): Reference
    {
        if ($contributor->exists && $contributor->isDirty(['id', 'reference_id'])) {
            throw new InvalidArgumentException('Contributor link identity is immutable.');
        }

        $reference = new Reference;
        $reference->setConnection($contributor->getConnectionName());
        $reference->setAttribute($reference->getKeyName(), $contributor->reference_id);

        return $this->lock->handle($reference);
    }
}
