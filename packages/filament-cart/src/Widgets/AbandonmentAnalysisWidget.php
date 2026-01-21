<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Abandonment analysis widget with multiple breakdowns.
 */
class AbandonmentAnalysisWidget extends Widget
{
    public string $activeTab = 'hour';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $interval = null;

    protected string $view = 'filament-cart::widgets.abandonment-analysis';

    protected int | string | array $columnSpan = 1;

    #[On('date-range-updated')]
    public function refresh(): void
    {
        // Widget will refresh on event
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getData(): array
    {
        $from = $this->resolveDateFrom();
        $to = $this->resolveDateTo();

        $service = app(CartAnalyticsService::class);
        $analysis = $service->getAbandonmentAnalysis($from, $to);

        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $dayLabels = [];
        foreach ($analysis->by_day_of_week as $day => $count) {
            $dayLabels[$days[$day]] = $count;
        }

        return [
            'analysis' => $analysis,
            'byHour' => $analysis->by_hour,
            'byDayOfWeek' => $dayLabels,
            'byCartValueRange' => $analysis->by_cart_value_range,
            'byItemsCount' => $analysis->by_items_count,
            'commonExitPoints' => $analysis->common_exit_points,
            'total' => $analysis->total_abandonments,
            'peakHour' => $this->findPeakHour($analysis->by_hour),
            'peakDay' => $this->findPeakDay($analysis->by_day_of_week),
            'topExitPoint' => $this->getTopExitPoint($analysis->common_exit_points),
        ];
    }

    private function resolveDateFrom(): Carbon
    {
        $value = $this->resolveQueryValue('dateFrom', $this->dateFrom);

        return $value !== '' ? Carbon::parse($value) : Carbon::now()->subDays(30);
    }

    private function resolveDateTo(): Carbon
    {
        $value = $this->resolveQueryValue('dateTo', $this->dateTo);

        return $value !== '' ? Carbon::parse($value) : Carbon::now();
    }

    private function resolveQueryValue(string $key, ?string $fallback): string
    {
        $queryValue = request()->query($key);

        if (is_string($queryValue) && $queryValue !== '') {
            return $queryValue;
        }

        return $fallback ?? '';
    }

    /**
     * @param  array<int, int>  $byHour
     */
    private function findPeakHour(array $byHour): string
    {
        if (empty($byHour)) {
            return 'N/A';
        }

        $peakHour = array_search(max($byHour), $byHour);

        return sprintf('%02d:00 - %02d:00', $peakHour, $peakHour + 1);
    }

    /**
     * @param  array<int, int>  $byDay
     */
    private function findPeakDay(array $byDay): string
    {
        if (empty($byDay)) {
            return 'N/A';
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $peakDay = array_search(max($byDay), $byDay);

        return $days[$peakDay] ?? 'N/A';
    }

    /**
     * @param  array<string, int>  $exitPoints
     */
    private function getTopExitPoint(array $exitPoints): string
    {
        if (empty($exitPoints)) {
            return 'Unknown';
        }

        return array_key_first($exitPoints);
    }
}
