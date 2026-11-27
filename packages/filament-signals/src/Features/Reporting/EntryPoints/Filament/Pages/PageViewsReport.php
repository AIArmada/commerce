<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Pages;

use AIArmada\Signals\Features\Ingestion\Api\TelemetryRead;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Reporting\Api\SegmentOptions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class PageViewsReport extends ReportPage implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Page Views';

    protected static ?string $title = 'Page Views';

    protected static ?string $slug = 'signals/page-views';

    protected string $view = 'filament-signals::pages.page-views-report';

    public static function getNavigationSort(): int
    {
        return (int) config('filament-signals.resources.navigation_sort.page_views', 15);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.page_views', true)
            && static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SignalEvent::pageViewRows(
                $this->signalSegmentId !== '' ? $this->signalSegmentId : null,
                $this->trackedPropertyId !== '' ? $this->trackedPropertyId : null,
                $this->parseFilterDate($this->dateFrom),
                $this->parseFilterDate($this->dateTo),
            ))
            ->defaultSort('views', 'desc')
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                TextColumn::make('page_path')
                    ->label('Path')
                    ->weight('medium'),
                TextColumn::make('page_url')
                    ->label('URL')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->url(fn (SignalEvent $record): ?string => $this->safeReportUrl($record->page_url ?? null), shouldOpenInNewTab: true),
                TextColumn::make('views')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('visitors')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('first_seen_at')
                    ->label('First Seen')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (mixed $state): ?string => $this->formatAggregateTimestamp($state))
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->formatStateUsing(fn (mixed $state): ?string => $this->formatAggregateTimestamp($state))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tracked_property_id')
                    ->label('Property')
                    ->options(app(TelemetryRead::class)->propertyOptions()),
                SelectFilter::make('signal_segment_id')
                    ->label('Segment')
                    ->options(app(SegmentOptions::class)->getSegmentOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $segmentId = is_string($data['value'] ?? null) ? $data['value'] : null;

                        return SignalEvent::applyReportSegment($query, $segmentId);
                    }),
                Filter::make('occurred_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $this->parseFilterDate($data['from'] ?? null);
                        $until = $this->parseFilterDate($data['until'] ?? null);

                        if ($from !== null && $until !== null && $from->isAfter($until)) {
                            [$from, $until] = [$until, $from];
                        }

                        return $query
                            ->when(
                                $from instanceof CarbonImmutable,
                                fn (Builder $query): Builder => $query->where('occurred_at', '>=', $from->startOfDay())
                            )
                            ->when(
                                $until instanceof CarbonImmutable,
                                fn (Builder $query): Builder => $query->where('occurred_at', '<', $until->startOfDay()->addDay())
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        $from = $this->parseFilterDate($data['from'] ?? null);
                        $until = $this->parseFilterDate($data['until'] ?? null);

                        if ($from instanceof CarbonImmutable) {
                            $indicators[] = 'From ' . $from->toFormattedDateString();
                        }

                        if ($until instanceof CarbonImmutable) {
                            $indicators[] = 'Until ' . $until->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                Filter::make('exclude_bots')
                    ->label('Exclude Bots')
                    ->toggle()
                    ->default(true)
                    ->query(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false)
                        ? $query->whereDoesntHave('session', fn (Builder $q): Builder => $q->whereNotNull('identified_as_bot_at'))
                        : $query),
            ])
            ->emptyStateHeading('No page views recorded yet')
            ->emptyStateDescription('Pageview data will appear here once the tracker starts sending events.');
    }

    private function parseFilterDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }
}
