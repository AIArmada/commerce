<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum PolicyEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::Deny => 'Deny',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Allow => 'Grants access when conditions are met',
            self::Deny => 'Denies access when conditions are met',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Allow => 'success',
            self::Deny => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Allow => 'heroicon-o-check-circle',
            self::Deny => 'heroicon-o-x-circle',
        };
    }

    public function isPermissive(): bool
    {
        return $this === self::Allow;
    }

    public function isRestrictive(): bool
    {
        return $this === self::Deny;
    }
}
