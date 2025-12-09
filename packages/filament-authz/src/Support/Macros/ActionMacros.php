<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support\Macros;

use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActionMacros
{
    public static function register(): void
    {
        Action::macro('requiresPermission', function (string $permission): static {
            /** @var Action $this */
            /** @phpstan-ignore return.type */
            return $this
                ->authorize(function () use ($permission): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $aggregator = app(PermissionAggregator::class);

                    return $aggregator->userHasPermission($user, $permission);
                })
                ->visible(function () use ($permission): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $aggregator = app(PermissionAggregator::class);

                    return $aggregator->userHasPermission($user, $permission);
                });
        });

        Action::macro('requiresRole', function (string | array $roles): static {
            /** @var Action $this */
            $rolesArray = is_array($roles) ? $roles : [$roles];

            /** @phpstan-ignore return.type, method.notFound */
            return $this
                ->authorize(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false)
                ->visible(fn (): bool => Auth::user()?->hasAnyRole($rolesArray) ?? false);
        });

        Action::macro('requiresAnyPermission', function (array $permissions): static {
            /** @var Action $this */
            /** @phpstan-ignore return.type */
            return $this
                ->authorize(function () use ($permissions): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $aggregator = app(PermissionAggregator::class);

                    return $aggregator->userHasAnyPermission($user, $permissions);
                })
                ->visible(function () use ($permissions): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $aggregator = app(PermissionAggregator::class);

                    return $aggregator->userHasAnyPermission($user, $permissions);
                });
        });

        Action::macro('requiresAllPermissions', function (array $permissions): static {
            /** @var Action $this */
            /** @phpstan-ignore return.type */
            return $this
                ->authorize(function () use ($permissions): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $aggregator = app(PermissionAggregator::class);

                    return $aggregator->userHasAllPermissions($user, $permissions);
                })
                ->visible(function () use ($permissions): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $aggregator = app(PermissionAggregator::class);

                    return $aggregator->userHasAllPermissions($user, $permissions);
                });
        });

        Action::macro('requiresTeamPermission', function (string $permission, string | int $teamId): static {
            /** @var Action $this */
            /** @phpstan-ignore return.type */
            return $this
                ->authorize(function () use ($permission, $teamId): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $contextAuth = app(ContextualAuthorizationService::class);

                    return $contextAuth->canInTeam($user, $permission, $teamId);
                })
                ->visible(function () use ($permission, $teamId): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    $contextAuth = app(ContextualAuthorizationService::class);

                    return $contextAuth->canInTeam($user, $permission, $teamId);
                });
        });

        Action::macro('requiresResourcePermission', function (string $permission, ?Model $resource = null): static {
            /** @var Action $this */
            /** @phpstan-ignore return.type */
            return $this
                ->authorize(function () use ($permission, $resource): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    if ($resource === null) {
                        $aggregator = app(PermissionAggregator::class);

                        return $aggregator->userHasPermission($user, $permission);
                    }

                    $contextAuth = app(ContextualAuthorizationService::class);

                    return $contextAuth->canForResource($user, $permission, $resource);
                })
                ->visible(function () use ($permission, $resource): bool {
                    $user = Auth::user();
                    if ($user === null) {
                        return false;
                    }

                    if ($resource === null) {
                        $aggregator = app(PermissionAggregator::class);

                        return $aggregator->userHasPermission($user, $permission);
                    }

                    $contextAuth = app(ContextualAuthorizationService::class);

                    return $contextAuth->canForResource($user, $permission, $resource);
                });
        });

        Action::macro('requiresOwnership', function (?Model $resource = null): static {
            /** @var Action $this */
            /** @phpstan-ignore return.type */
            return $this
                ->authorize(function () use ($resource): bool {
                    $user = Auth::user();
                    if ($user === null || $resource === null) {
                        return false;
                    }

                    $ownerId = $resource->getAttribute('user_id');

                    /** @phpstan-ignore method.notFound */
                    return $ownerId !== null && $ownerId === $user->getKey();
                })
                ->visible(function () use ($resource): bool {
                    $user = Auth::user();
                    if ($user === null || $resource === null) {
                        return false;
                    }

                    $ownerId = $resource->getAttribute('user_id');

                    /** @phpstan-ignore method.notFound */
                    return $ownerId !== null && $ownerId === $user->getKey();
                });
        });
    }
}
