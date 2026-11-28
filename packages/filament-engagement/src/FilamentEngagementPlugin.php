<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement;

use AIArmada\FilamentEngagement\Contracts\AdminPanelContribution;
use AIArmada\FilamentEngagement\Platform\ExtensionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentEngagementPlugin implements Plugin
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
        return 'filament-engagement';
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $widgets = [];

        foreach (app(ExtensionRegistry::class)->resolve('admin.panel.contributions', AdminPanelContribution::class) as $contribution) {
            if ($contribution instanceof AdminPanelContribution) {
                $resources += $contribution->resources();
                $widgets += $contribution->widgets();
            }
        }

        ksort($resources);
        ksort($widgets);
        $panel->resources(array_values($resources))->widgets(array_values($widgets));
    }

    public function boot(Panel $panel): void {}
}
