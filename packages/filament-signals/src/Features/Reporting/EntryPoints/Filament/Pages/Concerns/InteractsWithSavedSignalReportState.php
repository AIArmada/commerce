<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages\Concerns;

use AIArmada\FilamentSignals\Support\SignalsReportStateSanitizer;

trait InteractsWithSavedSignalReportState
{
    abstract protected function savedReportType(): string;

    protected function sanitizeSavedReportState(): void
    {
        /** @var string $savedReportId */
        $savedReportId = $this->savedReportId;

        $this->savedReportId = app(SignalsReportStateSanitizer::class)
            ->sanitizeSavedReportId($savedReportId, $this->savedReportType());
    }

    public function updatedSavedReportId(): void
    {
        $this->sanitizeSavedReportState();
    }
}
