<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ImpersonationBannerWidget extends Widget
{
    protected string $view = 'filament-authz::widgets.impersonation-banner';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();
        $superAdmin = (string) config('filament-authz.super_admin_role');

        /** @phpstan-ignore method.notFound */
        return $user?->hasRole($superAdmin) ?? false;
    }

    public function getCurrentRoleContext(): ?string
    {
        /** @phpstan-ignore property.notFound */
        return Auth::user()?->roles->pluck('name')->join(', ') ?? 'None';
    }
}
