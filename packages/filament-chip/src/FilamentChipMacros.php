<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use Closure;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

final class FilamentChipMacros
{
    public static function register(): void
    {
        if (! Panel::hasMacro('softShadow')) {
            Panel::macro('softShadow', Closure::bind(function (string $color = 'gray-200'): Panel {
                return $this->extraAttributes([
                    'class' => sprintf('shadow-lg shadow-%s/40 ring-1 ring-black/5', $color),
                ]);
            }, new Panel([]), Panel::class));
        }

        if (! Split::hasMacro('glow')) {
            Split::macro('glow', Closure::bind(function (string $glowColor = 'primary'): Split {
                return $this->extraAttributes([
                    'class' => sprintf('after:absolute after:inset-0 after:-z-10 after:rounded-2xl after:bg-gradient-to-r after:from-%s-500/20 after:to-transparent', $glowColor),
                ]);
            }, new Split([]), Split::class));
        }

        if (! Stack::hasMacro('carded')) {
            Stack::macro('carded', Closure::bind(function (): Stack {
                return $this->extraAttributes([
                    'class' => 'rounded-2xl border border-white/60 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5',
                ]);
            }, new Stack([]), Stack::class));
        }

        if (! Fieldset::hasMacro('inlineLabelled')) {
            Fieldset::macro('inlineLabelled', Closure::bind(function (): Fieldset {
                return $this->columns(2)->extraAttributes([
                    'class' => 'gap-x-8',
                ]);
            }, new Fieldset('inline'), Fieldset::class));
        }
    }
}
