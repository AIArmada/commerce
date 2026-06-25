<?php

declare(strict_types=1);

namespace AIArmada\Membership\Enums;

/**
 * @property array{id: string, name: string, label: string, spatie_role_name: string} $toArray
 */
enum MemberRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    public function spatieRoleName(): string
    {
        $mapping = config('membership.role_mapping', [
            'admin' => 'admin',
            'editor' => 'editor',
            'viewer' => 'viewer',
        ]);

        return $mapping[$this->value] ?? $this->value;
    }

    public static function fromSpatieRoleName(string $name): ?self
    {
        $mapping = config('membership.role_mapping', [
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
