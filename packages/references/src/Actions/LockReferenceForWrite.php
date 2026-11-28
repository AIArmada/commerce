<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Models\Reference;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use LogicException;

final readonly class LockReferenceForWrite
{
    public function handle(Reference $reference): Reference
    {
        if ($reference->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Reference writes require a transaction.');
        }

        if ($reference->exists && $reference->isDirty($reference->getKeyName())) {
            throw new InvalidArgumentException('Reference primary key is immutable.');
        }

        $locked = $reference->newQuery()->withoutOwnerScope()->whereKey($reference->getKey())->lockForUpdate()->firstOrFail();
        $config = Reference::ownerScopeConfig();

        if (! $config->enabled) {
            return $locked;
        }

        $owner = OwnerContext::resolveConfigured($config);

        if ($locked->isGlobal()) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new AuthorizationException('Explicit global context is required to write global references.');
            }

            return $locked;
        }

        if ($owner === null || ! $locked->belongsToOwner($owner)) {
            throw new AuthorizationException('Cross-owner reference write blocked.');
        }

        return $locked;
    }
}
