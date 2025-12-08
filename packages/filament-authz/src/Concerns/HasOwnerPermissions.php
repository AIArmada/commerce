<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;

/**
 * Trait for checking owner-based permissions on Eloquent models.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasOwnerPermissions
{
    /**
     * Check if a user can perform an action on this model based on ownership.
     *
     * @param  object  $user
     */
    public function canUserPerform($user, string $action): bool
    {
        // Get the contextual auth service
        $service = app(ContextualAuthorizationService::class);

        // Check standard permission first
        $permission = $this->getPermissionName($action);

        if ($service->canForResource($user, $permission, $this)) {
            return true;
        }

        // Check owner-specific permission
        if ($this->isOwnedBy($user)) {
            $ownPermission = $this->getOwnerPermissionName($action);

            return $service->canWithContext($user, $ownPermission, [
                'scope' => PermissionScope::Owner->value,
                'owner_id' => $user->getKey(),
            ]);
        }

        return false;
    }

    /**
     * Check if the model is owned by the given user.
     *
     * @param  object  $user
     */
    public function isOwnedBy($user): bool
    {
        $ownerKey = $this->getOwnerKeyName();

        return $this->getAttribute($ownerKey) === $user->getKey();
    }

    /**
     * Scope query to only include models owned by a user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  object  $user
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOwnedBy($query, $user)
    {
        return $query->where($this->getOwnerKeyName(), $user->getKey());
    }

    /**
     * Scope query to only include models the user can view.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  object  $user
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeViewableBy($query, $user)
    {
        $service = app(ContextualAuthorizationService::class);
        $permission = $this->getPermissionName('viewAny');

        // If user has global viewAny, return all
        if ($service->canWithContext($user, $permission, [])) {
            return $query;
        }

        // Otherwise, only return owned
        return $query->ownedBy($user);
    }

    /**
     * Get the permission name for an action.
     */
    protected function getPermissionName(string $action): string
    {
        $resourceName = $this->getResourceName();

        return "{$resourceName}.{$action}";
    }

    /**
     * Get the owner-specific permission name for an action.
     */
    protected function getOwnerPermissionName(string $action): string
    {
        $resourceName = $this->getResourceName();

        return "{$resourceName}.{$action}.own";
    }

    /**
     * Get the resource name for permission checking.
     */
    protected function getResourceName(): string
    {
        // Default to table name without prefix
        $table = $this->getTable();

        // Remove common prefixes if any
        $prefixes = ['authz_', 'inv_', 'vou_'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($table, $prefix)) {
                return mb_substr($table, mb_strlen($prefix));
            }
        }

        return $table;
    }

    /**
     * Get the owner key name (foreign key to user).
     */
    protected function getOwnerKeyName(): string
    {
        // Override this in your model if using a different column
        return 'user_id';
    }
}
