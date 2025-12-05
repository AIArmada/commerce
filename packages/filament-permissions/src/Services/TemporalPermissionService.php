<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Services;

use AIArmada\FilamentPermissions\Enums\PermissionScope;
use AIArmada\FilamentPermissions\Models\ScopedPermission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class TemporalPermissionService
{
    public function __construct(
        protected ContextualAuthorizationService $contextualAuth
    ) {}

    /**
     * Grant a temporary permission.
     *
     * @param  object  $user
     * @param  array<string, mixed>  $additionalConditions
     */
    public function grantTemporaryPermission(
        $user,
        string $permission,
        \DateTimeInterface $expiresAt,
        ?PermissionScope $scope = null,
        ?string $scopeValue = null,
        array $additionalConditions = []
    ): ScopedPermission {
        return $this->contextualAuth->grantScopedPermission(
            user: $user,
            permission: $permission,
            scope: $scope ?? PermissionScope::Temporal,
            scopeValue: $scopeValue ?? 'temporary',
            conditions: $additionalConditions,
            expiresAt: $expiresAt
        );
    }

    /**
     * Grant a permission that's valid for a specific duration.
     *
     * @param  object  $user
     * @param  array<string, mixed>  $additionalConditions
     */
    public function grantForDuration(
        $user,
        string $permission,
        int $minutes,
        ?PermissionScope $scope = null,
        ?string $scopeValue = null,
        array $additionalConditions = []
    ): ScopedPermission {
        return $this->grantTemporaryPermission(
            user: $user,
            permission: $permission,
            expiresAt: Carbon::now()->addMinutes($minutes),
            scope: $scope,
            scopeValue: $scopeValue,
            additionalConditions: $additionalConditions
        );
    }

    /**
     * Grant a permission valid during specific hours.
     *
     * @param  object  $user
     */
    public function grantDuringHours(
        $user,
        string $permission,
        int $startHour,
        int $endHour,
        ?\DateTimeInterface $expiresAt = null
    ): ScopedPermission {
        return $this->contextualAuth->grantScopedPermission(
            user: $user,
            permission: $permission,
            scope: PermissionScope::Temporal,
            scopeValue: "hours:{$startHour}-{$endHour}",
            conditions: [
                'time_range' => [
                    'start_hour' => $startHour,
                    'end_hour' => $endHour,
                ],
            ],
            expiresAt: $expiresAt
        );
    }

    /**
     * Grant a permission valid on specific days.
     *
     * @param  object  $user
     * @param  array<int>  $days  Days of week (0=Sunday, 6=Saturday)
     */
    public function grantOnDays(
        $user,
        string $permission,
        array $days,
        ?\DateTimeInterface $expiresAt = null
    ): ScopedPermission {
        return $this->contextualAuth->grantScopedPermission(
            user: $user,
            permission: $permission,
            scope: PermissionScope::Temporal,
            scopeValue: 'days:'.implode(',', $days),
            conditions: [
                'allowed_days' => $days,
            ],
            expiresAt: $expiresAt
        );
    }

    /**
     * Check if a user has an active temporal permission.
     *
     * @param  object  $user
     */
    public function hasActiveTemporaryPermission($user, string $permission): bool
    {
        $now = Carbon::now();

        $scopedPermissions = ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->whereHas('permission', fn ($q) => $q->where('name', $permission))
            ->active()
            ->get();

        foreach ($scopedPermissions as $scopedPermission) {
            if ($this->isActiveNow($scopedPermission, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all expiring permissions within a timeframe.
     *
     * @param  object  $user
     * @return Collection<int, ScopedPermission>
     */
    public function getExpiringPermissions($user, int $withinMinutes = 60): Collection
    {
        $threshold = Carbon::now()->addMinutes($withinMinutes);

        return ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->active()
            ->where('expires_at', '<=', $threshold)
            ->with('permission')
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Extend a temporary permission.
     *
     * @param  object  $user
     */
    public function extendPermission($user, string $permission, int $additionalMinutes): ?ScopedPermission
    {
        $scopedPermission = ScopedPermission::query()
            ->where('permissionable_type', $user::class)
            ->where('permissionable_id', $user->getKey())
            ->whereHas('permission', fn ($q) => $q->where('name', $permission))
            ->active()
            ->orderBy('expires_at', 'desc')
            ->first();

        if ($scopedPermission === null) {
            return null;
        }

        $currentExpiry = $scopedPermission->expires_at ?? Carbon::now();
        $newExpiry = Carbon::parse($currentExpiry)->addMinutes($additionalMinutes);
        $scopedPermission->expires_at = \Illuminate\Support\Carbon::parse($newExpiry);
        $scopedPermission->save();

        return $scopedPermission;
    }

    /**
     * Revoke all expired permissions.
     */
    public function revokeExpired(): int
    {
        return ScopedPermission::query()
            ->expired()
            ->delete();
    }

    /**
     * Check if a scoped permission is active right now.
     */
    protected function isActiveNow(ScopedPermission $scopedPermission, Carbon $now): bool
    {
        // Check expiration
        if ($scopedPermission->expires_at !== null && $now->isAfter($scopedPermission->expires_at)) {
            return false;
        }

        $conditions = $scopedPermission->conditions ?? [];

        // Check time range
        if (isset($conditions['time_range'])) {
            $startHour = $conditions['time_range']['start_hour'];
            $endHour = $conditions['time_range']['end_hour'];
            $currentHour = (int) $now->format('G');

            if ($startHour <= $endHour) {
                if ($currentHour < $startHour || $currentHour >= $endHour) {
                    return false;
                }
            } else {
                // Overnight range (e.g., 22-6)
                if ($currentHour < $startHour && $currentHour >= $endHour) {
                    return false;
                }
            }
        }

        // Check allowed days
        if (isset($conditions['allowed_days'])) {
            $currentDay = (int) $now->dayOfWeek;
            if (! in_array($currentDay, $conditions['allowed_days'], true)) {
                return false;
            }
        }

        return true;
    }
}
