<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Contracts\RewardRecipientResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Morph-map recipient resolver: the package default.
 *
 * Resolves existence through Laravel's morph map without querying a
 * concrete host model class and creates no external account. Hosts with
 * automated rails bind a resolver that also provisions external
 * recipient references.
 */
final class MorphRewardRecipientResolver implements RewardRecipientResolver
{
    public function exists(string $recipientType, string $recipientId): bool
    {
        $class = Relation::getMorphedModel($recipientType) ?? $recipientType;

        if ((! is_string($class) || ! class_exists($class)) && $recipientType === 'user') {
            $configured = config('auth.providers.users.model');

            $class = is_string($configured) ? $configured : $class;
        }

        if (! is_string($class) || ! class_exists($class)) {
            return false;
        }

        if (! is_subclass_of($class, Model::class)) {
            return false;
        }

        return $class::query()->whereKey($recipientId)->exists();
    }

    public function mutexKey(string $recipientType, string $recipientId): string
    {
        return hash('sha256', $recipientType . "\0" . $recipientId);
    }
}
