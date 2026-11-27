<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Actions;

use AIArmada\Growth\Settings\GrowthSettings;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

final readonly class UpdateGrowthSettings
{
    public function handle(bool $enabled): void
    {
        $user = Filament::auth()->user();

        if ($user === null || Gate::forUser($user)->denies('growth.settings.manage')) {
            throw new AuthorizationException;
        }

        try {
            $settings = app(GrowthSettings::class);
            $settings->experimentMiddlewareEnabled;
        } catch (MissingSettings) {
            $settings = new GrowthSettings(['experimentMiddlewareEnabled' => $enabled]);
        }

        $settings->experimentMiddlewareEnabled = $enabled;
        $settings->save();
    }
}
