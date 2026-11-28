<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback;

use AIArmada\FilamentFeedback\Contracts\AdminPanelContribution;
use AIArmada\FilamentFeedback\Platform\ExtensionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentFeedbackPlugin implements Plugin
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
        return 'filament-feedback';
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $pages = [];
        $widgets = [];

        foreach (app(ExtensionRegistry::class)->resolve('admin.panel.contributions', AdminPanelContribution::class) as $contribution) {
            if ($contribution instanceof AdminPanelContribution) {
                $resources += $contribution->resources();
                $pages += $contribution->pages();
                $widgets += $contribution->widgets();
            }
        }

        ksort($resources);
        ksort($pages);
        ksort($widgets);
        $panel->resources(array_values($resources))->pages(array_values($pages))->widgets(array_values($widgets));
    }

    public function boot(Panel $panel): void {}
}
