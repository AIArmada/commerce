<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Concerns;

use Filament\Pages\Page;
use Illuminate\Support\Str;

abstract class PortalPage extends Page
{
    use InteractsWithAffiliate;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-affiliates.portal.navigation_sort.' . static::navigationSortConfigKey());

        return is_numeric($sort) ? (int) $sort : null;
    }

    protected static function navigationSortConfigKey(): string
    {
        return Str::of(class_basename(static::class))
            ->after('Portal')
            ->snake()
            ->toString();
    }
}
