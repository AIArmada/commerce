<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum PermissionScope: string
{
    case Global = 'global';
    case Team = 'team';
    case Tenant = 'tenant';
    case Resource = 'resource';
    case Temporal = 'temporal';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global',
            self::Team => 'Team',
            self::Tenant => 'Tenant',
            self::Resource => 'Resource',
            self::Temporal => 'Temporal',
            self::Owner => 'Owner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Global => 'Permission applies globally without scope restrictions',
            self::Team => 'Permission is scoped to a specific team',
            self::Tenant => 'Permission is scoped to a specific tenant',
            self::Resource => 'Permission is scoped to a specific resource instance',
            self::Temporal => 'Permission is time-limited with expiration',
            self::Owner => 'Permission applies only to owned resources',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Global => 'heroicon-o-globe-alt',
            self::Team => 'heroicon-o-user-group',
            self::Tenant => 'heroicon-o-building-office',
            self::Resource => 'heroicon-o-cube',
            self::Temporal => 'heroicon-o-clock',
            self::Owner => 'heroicon-o-user',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Global => 'primary',
            self::Team => 'success',
            self::Tenant => 'info',
            self::Resource => 'warning',
            self::Temporal => 'danger',
            self::Owner => 'gray',
        };
    }

    public function requiresScopeId(): bool
    {
        return match ($this) {
            self::Global => false,
            default => true,
        };
    }

    public function supportsExpiration(): bool
    {
        return $this === self::Temporal;
    }
}
