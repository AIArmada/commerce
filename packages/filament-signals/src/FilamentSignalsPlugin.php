<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals;

use AIArmada\FilamentSignals\Contracts\AdminPanelContribution;
use AIArmada\FilamentSignals\Platform\ExtensionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentSignalsPlugin implements Plugin
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
        return 'filament-signals';
    }

    public function register(Panel $panel): void
    {
        $pages = [];
        $resources = [];

        foreach (app(ExtensionRegistry::class)->resolve('admin.panel.contributions', AdminPanelContribution::class) as $contribution) {
            if ($contribution instanceof AdminPanelContribution) {
                $pages += $contribution->pages();
                $resources += $contribution->resources();
            }
        }

        ksort($pages);
        ksort($resources);

        $panel->pages(array_values($pages))->resources(array_values($resources));
    }

    public function boot(Panel $panel): void {}
}
