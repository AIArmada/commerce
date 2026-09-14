<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\FilamentSignals\Pages\Concerns\InteractsWithSignalsDateRange;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

abstract class ReportPage extends Page
{
    use FormatsSignalsReportValues;
    use InteractsWithSignalsDateRange;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $trackedPropertyId = '';

    #[Url]
    public string $signalSegmentId = '';

    public function mount(): void
    {
        $this->initializeDefaultDateRange();
    }

    public static function canAccess(): bool
    {
        return FilamentPermission::hasAbility('signal-report.view');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-signals.navigation.group');
    }
}
