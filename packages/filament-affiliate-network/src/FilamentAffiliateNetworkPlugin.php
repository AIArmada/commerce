<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution;
use AIArmada\FilamentAffiliateNetwork\Platform\ExtensionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAffiliateNetworkPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-affiliate-network';
    }

    public function register(Panel $panel): void
    {
        $contributions = app(ExtensionRegistry::class)->resolve('panel.contributions', PanelContribution::class);
        usort($contributions, static fn (PanelContribution $a, PanelContribution $b): int => $a->priority() <=> $b->priority());

        foreach ($contributions as $contribution) {
            $panel->resources($contribution->resources())
                ->pages($contribution->pages())
                ->widgets($contribution->widgets());
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
