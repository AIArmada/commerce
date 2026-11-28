<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Enums\ReferenceContributorRole;
use AIArmada\References\Enums\ReferenceRecordKind;
use AIArmada\References\Models\Reference;
use InvalidArgumentException;
use RuntimeException;

final readonly class SyncReferenceContributors
{
    public function __construct(
        private LockReferenceForWrite $lock = new LockReferenceForWrite,
        private ValidateContributorIdentity $identity = new ValidateContributorIdentity,
    ) {}

    /**
     * @param  list<string>  $contributorIds
     */
    public function handle(Reference $reference, ReferenceContributorRole | string $role, string $contributorType, array $contributorIds): Reference
    {
        $role = $role instanceof ReferenceContributorRole ? $role : ReferenceContributorRole::tryFrom($role);

        if ($role === null) {
            throw new InvalidArgumentException('Unknown reference contributor role.');
        }

        $contributorType = $this->identity->type($contributorType);

        if (! $reference->exists) {
            throw new InvalidArgumentException('Contributors can only be synced on a persisted reference.');
        }

        $wanted = [];

        foreach ($contributorIds as $contributorId) {
            $id = mb_trim($contributorId);

            if ($id === '') {
                continue;
            }

            $wanted[$this->identity->id($id)] = true;
        }

        $reference->getConnection()->transaction(function () use ($reference, $role, $contributorType, $wanted): void {
            $locked = $this->lock->handle($reference);

            if ($role === ReferenceContributorRole::Author && $locked->record_kind !== ReferenceRecordKind::Work) {
                throw new InvalidArgumentException('Authors can only be stored on works; editions and parts inherit them.');
            }

            $existing = $locked->contributorsForRole($role)->get();

            foreach ($existing as $contributor) {
                if ($contributor->contributor_type !== $contributorType || ! isset($wanted[$contributor->contributor_id])) {
                    if (! $contributor->delete()) {
                        throw new RuntimeException('Contributor deletion was cancelled.');
                    }

                    continue;
                }

                unset($wanted[$contributor->contributor_id]);
            }

            foreach (array_keys($wanted) as $id) {
                $contributor = $locked->contributors()->create([
                    'contributor_type' => $contributorType,
                    'contributor_id' => $id,
                    'role' => $role,
                ]);

                if (! $contributor->exists) {
                    throw new RuntimeException('Contributor creation was cancelled.');
                }
            }
        });

        $reference->unsetRelation('contributors');

        return $reference;
    }
}
