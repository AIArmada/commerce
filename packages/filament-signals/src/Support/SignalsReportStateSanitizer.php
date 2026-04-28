<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final class SignalsReportStateSanitizer
{
    public function sanitizeTrackedPropertyId(?string $trackedPropertyId): string
    {
        if (! is_string($trackedPropertyId) || $trackedPropertyId === '') {
            return '';
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                TrackedProperty::class,
                $trackedPropertyId,
                includeGlobal: false,
            );

            return $trackedPropertyId;
        } catch (AuthorizationException | InvalidArgumentException) {
            return '';
        }
    }

    public function sanitizeSignalSegmentId(?string $signalSegmentId): string
    {
        if (! is_string($signalSegmentId) || $signalSegmentId === '') {
            return '';
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(
                SignalSegment::class,
                $signalSegmentId,
                includeGlobal: false,
            );

            return $signalSegmentId;
        } catch (AuthorizationException | InvalidArgumentException) {
            return '';
        }
    }

    public function sanitizeSavedReportId(?string $savedReportId, string $reportType): string
    {
        if (! is_string($savedReportId) || $savedReportId === '') {
            return '';
        }

        try {
            $savedReport = OwnerWriteGuard::findOrFailForOwner(
                SavedSignalReport::class,
                $savedReportId,
                includeGlobal: false,
            );
        } catch (AuthorizationException | InvalidArgumentException) {
            return '';
        }

        if ($savedReport->report_type !== $reportType || ! $savedReport->is_active) {
            return '';
        }

        return $savedReportId;
    }
}
