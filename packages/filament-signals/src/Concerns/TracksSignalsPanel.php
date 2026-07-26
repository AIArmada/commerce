<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Concerns;

use AIArmada\Signals\Support\Browser\SignalsTrackerRenderer;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

trait TracksSignalsPanel
{
    protected function trackSignalsForPanel(Panel $panel, string $panelId): Panel
    {
        return $panel->renderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => app(SignalsTrackerRenderer::class)->render([
                'properties' => ['surface' => $panelId],
            ]),
        );
    }
}
