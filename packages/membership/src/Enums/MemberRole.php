<?php

declare(strict_types=1);

namespace AIArmada\Membership\Enums;

/**
 * @property array{id: string, name: string, label: string, spatie_role_name: string} $toArray
 */
enum MemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Permissions granted to this role.
     * '*' means all permissions. Apps should override via config if needed.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return config('membership.role_permissions', [
            'owner' => ['*'],
            'admin' => ['update', 'manage-members'],
            'editor' => ['update'],
            'viewer' => ['view'],
        ])[$this->value] ?? $this->defaultPermissions();
    }

    /**
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Admin => ['update', 'manage-members'],
            self::Editor => ['update'],
            self::Viewer => ['view'],
        };
    }

    public function spatieRoleName(): string
    {
        $mapping = config('membership.role_mapping', [
            'owner' => 'owner',
            'admin' => 'admin',
            'editor' => 'editor',
            'viewer' => 'viewer',
        ]);

        return $mapping[$this->value] ?? $this->value;
    }

    public static function fromSpatieRoleName(string $name): ?self
    {
        $mapping = config('membership.role_mapping', [
            'owner' => 'owner',
            'admin' => 'admin',
            'editor' => 'editor',
            'viewer' => 'viewer',
        ]);

        $reverse = array_flip($mapping);

        $alias = $reverse[$name] ?? null;

        if ($alias === null) {
            return null;
        }

        return self::tryFrom($alias);
    }
}
