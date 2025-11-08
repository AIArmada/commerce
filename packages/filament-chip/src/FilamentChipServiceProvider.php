<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentChipServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-chip')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentChipPlugin::class);
    }

    public function packageBooted(): void
    {
        $this->registerFilamentMacros();
    }

    /**
     * Register custom Filament component macros for enhanced styling.
     */
    private function registerFilamentMacros(): void
    {
        $this->registerPanelMacros();
        $this->registerSplitMacros();
        $this->registerStackMacros();
        $this->registerFieldsetMacros();
    }

    /**
     * Register Panel component macros.
     */
    private function registerPanelMacros(): void
    {
        if (! Panel::hasMacro('softShadow')) {
            Panel::macro('softShadow', fn (string $color = 'gray-200'): Panel => $this->extraAttributes([ // @phpstan-ignore method.notFound
                'class' => sprintf('shadow-lg shadow-%s/40 ring-1 ring-black/5', $color),
            ]));
        }
    }

    /**
     * Register Split component macros.
     */
    private function registerSplitMacros(): void
    {
        if (! Split::hasMacro('glow')) {
            Split::macro('glow', fn (string $glowColor = 'primary'): Split => $this->extraAttributes([ // @phpstan-ignore method.notFound
                'class' => sprintf('after:absolute after:inset-0 after:-z-10 after:rounded-2xl after:bg-gradient-to-r after:from-%s-500/20 after:to-transparent', $glowColor),
            ]));
        }
    }

    /**
     * Register Stack component macros.
     */
    private function registerStackMacros(): void
    {
        if (! Stack::hasMacro('carded')) {
            Stack::macro('carded', fn (): Stack => $this->extraAttributes([ // @phpstan-ignore method.notFound
                'class' => 'rounded-2xl border border-white/60 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5',
            ]));
        }
    }

    /**
     * Register Fieldset component macros.
     */
    private function registerFieldsetMacros(): void
    {
        if (! Fieldset::hasMacro('inlineLabelled')) {
            Fieldset::macro('inlineLabelled', fn (): Fieldset => $this->columns(2)->extraAttributes([ // @phpstan-ignore method.notFound
                'class' => 'gap-x-8',
            ]));
        }
    }
}
