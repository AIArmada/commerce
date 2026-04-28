<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages\Concerns;

use AIArmada\FilamentSignals\Support\SignalsReportStateSanitizer;

trait InteractsWithSavedSignalReportState
{
    abstract protected function savedReportType(): string;

    protected function sanitizeSavedReportState(): void
    {
        if (! property_exists($this, 'savedReportId')) {
            return;
        }

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
