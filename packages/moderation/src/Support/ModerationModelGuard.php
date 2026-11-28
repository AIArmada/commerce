<?php

declare(strict_types=1);

namespace AIArmada\Moderation\Support;

use AIArmada\CommerceSupport\Contracts\OwnerScopeConfigurable;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final readonly class ModerationModelGuard
{
    public function validateTarget(Model $model): void
    {
        if (! $model->exists || $model->getKey() === null) {
            throw new InvalidArgumentException('Moderation requires a persisted target or actor.');
        }

        if (! config('moderation.owner.enabled', true)) {
            $model->newQuery()->whereKey($model->getKey())->firstOrFail();

            return;
        }

        if ($model instanceof OwnerScopeConfigurable && ! $model::ownerScopeConfig()->enabled) {
            $model->newQuery()->whereKey($model->getKey())->firstOrFail();

            return;
        }

        if (! $model instanceof OwnerScopeConfigurable && ! method_exists($model::class, 'ownerScopeConfig')) {
            $model->newQuery()->whereKey($model->getKey())->firstOrFail();

            return;
        }

        OwnerWriteGuard::findOrFailForOwner($model::class, $model->getKey());
    }

    public function validateActor(Model $actor): void
    {
        $this->assertAllowedActor($actor::class, $actor->getMorphClass());
        $this->validateTarget($actor);
    }

    public function resolveActor(?string $id, ?string $type): ?Model
    {
        if ($id === null && $type === null) {
            return null;
        }

        if ($id === null || $type === null) {
            throw new InvalidArgumentException('Both moderation actor ID and type are required.');
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException('Invalid moderation actor type.');
        }

        $this->assertAllowedActor($class, $type);

        if (! config('moderation.owner.enabled', true)) {
            return (new $class)->newQuery()->findOrFail($id);
        }

        if (method_exists($class, 'ownerScopeConfig') && $class::ownerScopeConfig()->enabled) {
            return OwnerWriteGuard::findOrFailForOwner($class, $id);
        }

        return (new $class)->newQuery()->findOrFail($id);
    }

    public function validatePayload(?string $notes, ?array $metadata): void
    {
        if ($notes !== null && mb_strlen($notes) > 10000) {
            throw new InvalidArgumentException('Moderation notes must not exceed 10000 characters.');
        }

        if ($metadata === null) {
            return;
        }

        if (count($metadata) > 50) {
            throw new InvalidArgumentException('Moderation metadata must not exceed 50 entries.');
        }

        $encoded = json_encode($metadata);

        if ($encoded === false || mb_strlen($encoded) > 65535) {
            throw new InvalidArgumentException('Moderation metadata must not exceed 65535 bytes.');
        }
    }

    private function assertAllowedActor(string $class, string $type): void
    {
        /** @var list<string> $allowed */
        $allowed = config('moderation.actors.allowed_types', []);

        if ($allowed !== [] && ! in_array($class, $allowed, true) && ! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('The actor type [%s] is not allowed to moderate.', $type));
        }
    }
}
