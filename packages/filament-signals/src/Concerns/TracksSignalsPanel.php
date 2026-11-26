<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Concerns;

use AIArmada\Signals\Features\Browser\Api\TrackerMarkup;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

trait TracksSignalsPanel
{
    protected function trackSignalsForPanel(Panel $panel, string $panelId): Panel
    {
        return $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => app(TrackerMarkup::class)->render([
                'properties' => ['surface' => $panelId],
            ]),
        );
    }
}
