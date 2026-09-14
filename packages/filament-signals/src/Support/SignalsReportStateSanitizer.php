<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Throwable;

final class SignalsReportStateSanitizer
{
    public const MAX_DATE_RANGE_DAYS = 366;

    public function __construct(private readonly SignalsModelReferenceGuard $referenceGuard) {}

    /**
     * Sanitize the tamperable #[Url] date range: strict Y-m-d only, ordered,
     * and clamped to at most MAX_DATE_RANGE_DAYS so forged values can
     * neither 500 the page nor trigger multi-year heavy reports.
     *
     * @return array{from: string, to: string}
     */
    public function sanitizeDateRange(?string $from, ?string $to): array
    {
        $now = CarbonImmutable::now();
        $defaultFrom = $now->subDays(29)->toDateString();
        $defaultTo = $now->toDateString();

        $parsedFrom = $this->parseDate($from) ?? $defaultFrom;
        $parsedTo = $this->parseDate($to) ?? $defaultTo;

        if ($parsedFrom > $parsedTo) {
            [$parsedFrom, $parsedTo] = [$parsedTo, $parsedFrom];
        }

        $fromDate = CarbonImmutable::parse($parsedFrom);
        $toDate = CarbonImmutable::parse($parsedTo);

        if ($fromDate->diffInDays($toDate) >= self::MAX_DATE_RANGE_DAYS) {
            $parsedFrom = $toDate->subDays(self::MAX_DATE_RANGE_DAYS - 1)->toDateString();
        }

        return ['from' => $parsedFrom, 'to' => $parsedTo];
    }

    private function parseDate(?string $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $parsed->format('Y-m-d') === $value ? $value : null;
    }

    public function sanitizeTrackedPropertyId(?string $trackedPropertyId): string
    {
        if (! is_string($trackedPropertyId) || $trackedPropertyId === '') {
            return '';
        }

        try {
            $this->referenceGuard->findOrFail(
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
            $this->referenceGuard->findOrFail(
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
            $savedReport = $this->referenceGuard->findOrFail(
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
