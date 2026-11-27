<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Features\ContactMethods\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use RuntimeException;

final readonly class MoveContactMethodAction
{
    public function __construct(
        private SynchronizePrimaryContactMethodAction $persist,
        private ContactingModelReferenceGuard $references,
    ) {}

    public function execute(ContactMethod $record, Model $target): ContactMethod
    {
        return $record->getConnection()->transaction(function () use ($record, $target): ContactMethod {
            if (ContactMethod::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(ContactMethod::class, $record->getKey());
            }

            $target = $this->references->resolve($target->getMorphClass(), (string) $target->getKey());
            if (! $target instanceof Model) {
                throw new RuntimeException('Contact target must exist.');
            }

            if ($target->getConnection() !== $record->getConnection()) {
                throw new LogicException('Contact moves require a shared database connection.');
            }

            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $record = ContactMethod::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($record->is_primary && ContactMethod::query()
                ->where('contactable_type', $target->getMorphClass())
                ->where('contactable_id', $target->getKey())
                ->where('type', $record->type)
                ->where('purpose', $record->purpose)
                ->whereKeyNot($record->getKey())
                ->where('is_primary', true)
                ->exists()) {
                $record->is_primary = false;
            }

            $record->contactable()->associate($target);
            if (! $this->persist->execute($record)) {
                throw new RuntimeException('Contact move was cancelled.');
            }

            return $record;
        });
    }
}
