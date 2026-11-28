<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting;

use AIArmada\FilamentContacting\Contracts\AdminPanelContribution;
use AIArmada\FilamentContacting\Platform\ExtensionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentContactingPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-contacting';
    }

    public function register(Panel $panel): void
    {
        if (! config('filament-contacting.features.standalone_resources', false)) {
            return;
        }

        $resources = [];

        foreach (app(ExtensionRegistry::class)->resolve('admin.panel.contributions', AdminPanelContribution::class) as $contribution) {
            if ($contribution instanceof AdminPanelContribution) {
                $resources += $contribution->resources();
            }
        }

        ksort($resources);
        $panel->resources(array_values($resources));
    }

    public function boot(Panel $panel): void {}
}
