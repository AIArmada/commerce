<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * Standard owner scoping for cart models.
 *
 * - Uses commerce-support HasOwner columns: owner_type / owner_id
 * - When cart.owner.enabled=true, scopeForOwner() resolves owner via OwnerContext
 * - When enabled and no owner context exists, saves are blocked (fail-fast)
 */
trait HasCartOwner
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'cart.owner';

    public static function ownerScopingEnabled(): bool
    {
        return (bool) config('cart.owner.enabled', false);
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

    public static function hasExplicitGlobalOwnerContext(): bool
    {
        return self::ownerScopingEnabled() && OwnerContext::isExplicitGlobal();
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

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }
}
