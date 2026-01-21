<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use RuntimeException;

/**
 * Standard owner scoping for filament-cart models.
 *
 * - Uses commerce-support HasOwner columns: owner_type / owner_id
 * - When filament-cart.owner.enabled=true, scopeForOwner() resolves owner via OwnerContext
 * - When enabled and no owner context exists, saves are blocked (fail-fast)
 *
 * @phpstan-ignore trait.unused
 */
trait HasFilamentCartOwner
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'filament-cart.owner';

    public static function ownerScopingEnabled(): bool
    {
        return (bool) config('filament-cart.owner.enabled', false);
    }

    public static function resolveCurrentOwner(): ?EloquentModel
    {
        if (! self::ownerScopingEnabled()) {
            return null;
        }

        /** @var EloquentModel|null $owner */
        $owner = OwnerContext::resolve();

        return $owner;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner = null, bool $includeGlobal = false): Builder
    {
        if (! self::ownerScopingEnabled()) {
            return $query;
        }

        if ($owner === null) {
            $owner = self::resolveCurrentOwner();
        }

        $includeGlobal = $includeGlobal && (bool) config('filament-cart.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    protected static function bootHasFilamentCartOwner(): void
    {
        static::saving(function (Model $model): void {
            if (! static::ownerScopingEnabled()) {
                return;
            }

            if ($model->getAttribute('owner_type') !== null && $model->getAttribute('owner_id') !== null) {
                return;
            }

            $owner = static::resolveCurrentOwner();

            if ($owner === null) {
                throw new RuntimeException(sprintf(
                    '%s requires an owner context when filament-cart.owner.enabled=true.',
                    $model::class
                ));
            }

            /** @var static $scopedModel */
            $scopedModel = $model;
            $scopedModel->assignOwner($owner);
        });
    }
}
