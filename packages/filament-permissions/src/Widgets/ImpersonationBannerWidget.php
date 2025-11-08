<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Widgets;

use Filament\Widgets\Widget;

class ImpersonationBannerWidget extends Widget
{
    protected string $view = 'filament-permissions::widgets.impersonation-banner';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        $superAdmin = (string) config('filament-permissions.super_admin_role');

        return $user?->hasRole($superAdmin) ?? false;
    }

    public function getCurrentRoleContext(): ?string
    {
        // Placeholder for future impersonation logic
        return auth()->user()?->roles->pluck('name')->join(', ') ?? 'None';
    }
}
