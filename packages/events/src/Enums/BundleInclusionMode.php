<?php

declare(strict_types=1);

namespace AIArmada\Events\Enums;

enum BundleInclusionMode: string
{
    case Required = 'required';
    case Optional = 'optional';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Required (auto-added to cart)',
            self::Optional => 'Optional (customer can add)',
        };
    }

    public function isRequired(): bool
    {
        return $this === self::Required;
    }
}
