<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\RecipientRouting\Queries;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class RecipientReferenceGuard
{
    public function resolve(Model $recipient): Model
    {
        if (! $recipient->exists || $recipient->getKey() === null) {
            throw new InvalidArgumentException('Recipient must be persisted.');
        }

        $class = $recipient::class;

        if (method_exists($class, 'ownerScopeConfig')) {
            /** @var OwnerScopeConfig $config */
            $config = $class::ownerScopeConfig();

            if ($config->enabled) {
                return OwnerWriteGuard::findOrFailForOwner($class, $recipient->getKey());
            }
        }

        return $recipient->newQuery()->whereKey($recipient->getKey())->firstOrFail();
    }
}
