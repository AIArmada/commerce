<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages;

use AIArmada\FilamentSignals\Pages\Concerns\FormatsSignalsReportValues;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Services\PageViewReportService;
use AIArmada\Signals\Services\SignalSegmentReportFilter;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PageViewsReport extends Page implements HasTable
{
    use FormatsSignalsReportValues;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Page Views';

    protected static ?string $title = 'Page Views';

    protected static ?string $slug = 'signals/page-views';

    protected string $view = 'filament-signals::pages.page-views-report';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.page_views', 15);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-signals.features.page_views', true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(app(PageViewReportService::class)->getTableQuery())
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
                    ->url(fn (SignalEvent $record): ?string => is_string($record->page_url ?? null) ? $record->page_url : null, shouldOpenInNewTab: true),
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
                    ->options(app(PageViewReportService::class)->getTrackedPropertyOptions()),
                SelectFilter::make('signal_segment_id')
                    ->label('Segment')
                    ->options(app(SignalSegmentReportFilter::class)->getSegmentOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $segmentId = is_string($data['value'] ?? null) ? $data['value'] : null;

                        return app(SignalSegmentReportFilter::class)->applyToEventQuery($query, $segmentId);
                    }),
                Filter::make('occurred_at')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('occurred_at', '>=', (string) $data['from'])
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('occurred_at', '<=', (string) $data['until'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = 'From ' . CarbonImmutable::parse((string) $data['from'])->toFormattedDateString();
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = 'Until ' . CarbonImmutable::parse((string) $data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                Filter::make('exclude_bots')
                    ->label('Exclude Bots')
                    ->toggle()
                    ->default(true)
                    ->query(fn (Builder $query, array $data): Builder => ($data['isActive'] ?? false)
                        ? $query->whereDoesntHave('session', fn (Builder $q): Builder => $q->where('is_bot', true))
                        : $query),
            ])
            ->emptyStateHeading('No page views recorded yet')
            ->emptyStateDescription('Pageview data will appear here once the tracker starts sending events.');
    }
}
