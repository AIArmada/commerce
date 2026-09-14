<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Gate the global pricing settings page behind a configurable ability.
 *
 * Pricing settings are Spatie settings: a single global row shared by every
 * tenant. Only holders of the configured ability may open or save the page.
 */
final class PricingSettingsAccess
{
    public static function allows(?Authorizable $user = null): bool
    {
        $ability = config('filament-pricing.authorization.settings_ability');

        if (! is_string($ability) || $ability === '') {
            return false;
        }

        $actor = $user ?? Filament::auth()->user();

        return $actor instanceof Authorizable && $actor->can($ability);
    }
}
