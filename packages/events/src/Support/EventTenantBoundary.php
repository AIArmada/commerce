<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final class EventTenantBoundary
{
    public static function assertWritable(Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        if ($owner instanceof Model
            && $model->getMorphClass() === $owner->getMorphClass()
            && (string) $model->getKey() === (string) $owner->getKey()) {
            return;
        }

        if ($model instanceof Event) {
            EventWriteGuard::findOrFail($model);

            return;
        }

        if (! self::hasEnforcedBoundary($model::class)) {
            throw new AuthorizationException(sprintf(
                '%s does not expose an owner-safe boundary.',
                $model::class,
            ));
        }

        $visible = $model::query()->find($model->getKey());

        if (! $visible instanceof Model) {
            throw new AuthorizationException(sprintf(
                'Cross-owner write blocked for %s.',
                $model::class,
            ));
        }

        if (method_exists($visible, 'isGlobal')
            && $visible->isGlobal()
            && ! OwnerContext::isExplicitGlobal()) {
            throw new AuthorizationException(sprintf(
                'Explicit global owner context is required to write children of global %s records.',
                $model::class,
            ));
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function hasEnforcedBoundary(string $modelClass): bool
    {
        if (EventOwnerScope::supports($modelClass)
            || EventSubmissionOwnerScope::supports($modelClass)) {
            return true;
        }

        return method_exists($modelClass, 'ownerScopeConfig')
            && $modelClass::ownerScopeConfig()->enabled;
    }

    private static function enabled(): bool
    {
        return (bool) config('events.features.owner.enabled', true);
    }
}
