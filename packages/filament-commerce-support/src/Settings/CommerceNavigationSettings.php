<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Settings;

use Spatie\LaravelSettings\Settings;

class CommerceNavigationSettings extends Settings
{
    public array $groups = [];

    public array $overrides = [];

    public static function group(): string
    {
        return 'commerce-navigation';
    }
}
