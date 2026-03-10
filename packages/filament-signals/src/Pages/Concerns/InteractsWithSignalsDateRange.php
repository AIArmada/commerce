<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages\Concerns;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

trait InteractsWithSignalsDateRange
{
    protected function initializeDefaultDateRange(): void
    {
        if ($this->dateFrom === '') {
            $this->dateFrom = CarbonImmutable::now()->subDays(29)->toDateString();
        }

        if ($this->dateTo === '') {
            $this->dateTo = CarbonImmutable::now()->toDateString();
        }
    }

    /**
     * @param  array<string, string>  $trackedPropertyOptions
     * @param  array<string, string>  $signalSegmentOptions
     * @return array<int, Action>
     */
    protected function getDateRangeHeaderActions(array $trackedPropertyOptions = [], array $signalSegmentOptions = []): array
    {
        return [
            Action::make('filters')
                ->label('Filters')
                ->icon(Heroicon::OutlinedCalendar)
                ->form([
                    DatePicker::make('from')
                        ->label('From')
                        ->default($this->dateFrom)
                        ->required(),
                    DatePicker::make('to')
                        ->label('To')
                        ->default($this->dateTo)
                        ->required(),
                    Select::make('tracked_property_id')
                        ->label('Property')
                        ->options($trackedPropertyOptions)
                        ->default($this->trackedPropertyId !== '' ? $this->trackedPropertyId : null)
                        ->searchable(),
                    Select::make('signal_segment_id')
                        ->label('Segment')
                        ->options($signalSegmentOptions)
                        ->default($this->signalSegmentId !== '' ? $this->signalSegmentId : null)
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $this->dateFrom = CarbonImmutable::parse((string) $data['from'])->toDateString();
                    $this->dateTo = CarbonImmutable::parse((string) $data['to'])->toDateString();
                    $this->trackedPropertyId = is_string($data['tracked_property_id'] ?? null)
                        ? $data['tracked_property_id']
                        : '';
                    $this->signalSegmentId = is_string($data['signal_segment_id'] ?? null)
                        ? $data['signal_segment_id']
                        : '';
                }),
            Action::make('last7days')
                ->label('Last 7 Days')
                ->outlined()
                ->action(fn (): bool => $this->applyQuickRange(7)),
            Action::make('last30days')
                ->label('Last 30 Days')
                ->outlined()
                ->action(fn (): bool => $this->applyQuickRange(30)),
            Action::make('last90days')
                ->label('Last 90 Days')
                ->outlined()
                ->action(fn (): bool => $this->applyQuickRange(90)),
        ];
    }

    protected function applyQuickRange(int $days): bool
    {
        $this->dateFrom = CarbonImmutable::now()->subDays($days - 1)->toDateString();
        $this->dateTo = CarbonImmutable::now()->toDateString();

        return true;
    }
}
