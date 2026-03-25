<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\SavedSignalReportDefinition;
use AIArmada\Signals\Services\SignalRouteCatalog;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class SavedSignalReportResource extends Resource
{
    protected static ?string $model = SavedSignalReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bookmark-square';

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 32;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SavedSignalReport>
     */
    public static function getEloquentQuery(): Builder
    {
        return SavedSignalReport::query()->forOwner()->with(['trackedProperty', 'segment']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Saved Report')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('report_type')
                        ->options(SavedSignalReportDefinition::reportTypeOptions())
                        ->required(),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Tracked Property')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                TrackedProperty::query()->forOwner()->select('id')
                            ),
                        )
                        ->live()
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('signal_segment_id')
                        ->label('Segment')
                        ->relationship(
                            name: 'segment',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                SignalSegment::query()->forOwner()->select('id')
                            ),
                        )
                        ->searchable()
                        ->preload(),

                    Forms\Components\Toggle::make('is_shared')
                        ->default(false),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('filters')
                        ->schema([
                            Forms\Components\Select::make('key')
                                ->options(SavedSignalReportDefinition::filterFieldOptions())
                                ->required()
                                ->distinct(),
                            Forms\Components\TextInput::make('value')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('YYYY-MM-DD'),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->addActionLabel('Add Filter')
                        ->helperText('Supported filters: From Date and To Date.'),

                    Section::make('Funnel Settings')
                        ->description('Build the journey step by step using an existing goal, a page route, a simple event name, or a detailed event rule.')
                        ->schema([
                            Forms\Components\Repeater::make('settings.funnel_steps')
                                ->label('Funnel steps')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('Step name')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('Example: Viewed the event page')
                                        ->helperText('This is the name people will see in the funnel report.'),
                                    Forms\Components\Select::make('step_type')
                                        ->label('How this step should be matched')
                                        ->options([
                                            'event' => 'A single event name',
                                            'goal' => 'An existing goal',
                                            'route' => 'A page or route',
                                            'conditions' => 'An event with extra rules',
                                        ])
                                        ->default('event')
                                        ->live()
                                        ->helperText('Choose the simplest option that matches what you want this step to count.')
                                        ->afterStateHydrated(function (Set $set, Get $get, mixed $state): void {
                                            if (is_string($state) && $state !== '') {
                                                return;
                                            }

                                            if (filled($get('goal_slug'))) {
                                                $set('step_type', 'goal');

                                                return;
                                            }

                                            if (filled($get('route_name'))) {
                                                $set('step_type', 'route');

                                                return;
                                            }

                                            if (filled($get('conditions'))) {
                                                $set('step_type', 'conditions');

                                                return;
                                            }

                                            $set('step_type', 'event');
                                        }),
                                    Forms\Components\Select::make('goal_slug')
                                        ->label('Goal to count')
                                        ->options(function (Get $get): array {
                                            $trackedPropertyId = $get('../../tracked_property_id');

                                            return SignalGoal::query()
                                                ->forOwner()
                                                ->where('is_active', true)
                                                ->when(
                                                    filled($trackedPropertyId),
                                                    fn (Builder $query): Builder => $query->where(function (Builder $goalQuery) use ($trackedPropertyId): void {
                                                        $goalQuery->where('tracked_property_id', $trackedPropertyId)
                                                            ->orWhereNull('tracked_property_id');
                                                    }),
                                                )
                                                ->orderBy('name')
                                                ->pluck('name', 'slug')
                                                ->all();
                                        })
                                        ->searchable()
                                        ->preload()
                                        ->helperText(fn (Get $get): string => filled($get('../../tracked_property_id'))
                                            ? 'Shows active goals for the selected tracked property, plus shared goals.'
                                            : 'Choose a tracked property first if you want a shorter list. Without one, all active goals are shown.')
                                        ->visible(fn (Get $get): bool => $get('step_type') === 'goal'),
                                    Forms\Components\Select::make('route_name')
                                        ->label('Page or route')
                                        ->options(fn (): array => app(SignalRouteCatalog::class)->options())
                                        ->searchable()
                                        ->preload()
                                        ->helperText('Choose a page from your site. Dynamic routes are matched by their path prefix automatically.')
                                        ->visible(fn (Get $get): bool => $get('step_type') === 'route'),
                                    Forms\Components\TextInput::make('event_name')
                                        ->label('Event name')
                                        ->helperText('Use this for event-based steps. Leave it empty when you are using a goal or a page route.')
                                        ->maxLength(255)
                                        ->placeholder('Example: page_view or affiliate.conversion.recorded')
                                        ->visible(fn (Get $get): bool => in_array($get('step_type'), ['event', 'conditions'], true)),
                                    Forms\Components\TextInput::make('event_category')
                                        ->label('Event category (optional)')
                                        ->maxLength(100)
                                        ->placeholder('Example: conversion')
                                        ->helperText('Optional. Use this only when the event name on its own is not specific enough.')
                                        ->visible(fn (Get $get): bool => in_array($get('step_type'), ['event', 'conditions'], true)),
                                    Forms\Components\Select::make('condition_match_type')
                                        ->label('How extra rules should match')
                                        ->options([
                                            'all' => 'All rules must match',
                                            'any' => 'Any rule can match',
                                        ])
                                        ->default('all')
                                        ->helperText('Use all rules for a strict step. Use any rule if one matching condition is enough.')
                                        ->visible(fn (Get $get): bool => $get('step_type') === 'conditions'),
                                    Forms\Components\Repeater::make('conditions')
                                        ->label('Extra rules')
                                        ->helperText('Add the extra checks that define this step more precisely.')
                                        ->schema([
                                            Forms\Components\TextInput::make('field')
                                                ->label('Field to check')
                                                ->required()
                                                ->maxLength(255)
                                                ->placeholder('Example: path or properties.checkout.gateway')
                                                ->helperText('You can use built-in fields like path, url, source, event_name, or your own properties.* values.'),
                                            Forms\Components\Select::make('operator')
                                                ->label('Compare using')
                                                ->options([
                                                    'equals' => 'Equals',
                                                    'not_equals' => 'Not Equals',
                                                    'contains' => 'Contains',
                                                    'starts_with' => 'Starts With',
                                                    'ends_with' => 'Ends With',
                                                    'greater_than' => 'Greater Than',
                                                    'greater_than_or_equal' => 'Greater Than or Equal',
                                                    'less_than' => 'Less Than',
                                                    'less_than_or_equal' => 'Less Than or Equal',
                                                    'in' => 'In List',
                                                ])
                                                    ->helperText('Use In List for comma-separated values like whatsapp,telegram,email.')
                                                ->required(),
                                            Forms\Components\TextInput::make('value')
                                                    ->label('Value to match')
                                                ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Example: /majlis or telegram'),
                                        ])
                                        ->columns(3)
                                                ->addActionLabel('Add rule')
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => $get('step_type') === 'conditions'),
                                ])
                                ->columns(3)
                                ->columnSpanFull()
                                            ->addActionLabel('Add step')
                                            ->helperText('Each step is one milestone in the journey you want to measure. Leave this empty to use the starter funnel template.'),
                            Forms\Components\TextInput::make('settings.step_window_minutes')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('Optional: future funnel analysis can use this as the maximum time allowed between steps.'),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'conversion_funnel')
                        ->columnSpanFull(),

                    Section::make('Acquisition Settings')
                        ->schema([
                            Forms\Components\Select::make('settings.attribution_model')
                                ->options(SavedSignalReportDefinition::attributionModelOptions())
                                ->default(SavedSignalReportDefinition::ATTRIBUTION_MODEL_EVENT)
                                ->required(),
                            Forms\Components\TextInput::make('settings.conversion_event_name')
                                ->default(SavedSignalReportDefinition::conversionEventName(null))
                                ->required()
                                ->helperText('Defaults to the configured primary outcome event.')
                                ->maxLength(255),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'acquisition')
                        ->columns(2)
                        ->columnSpanFull(),

                    Section::make('Journey Settings')
                        ->schema([
                            Forms\Components\Select::make('settings.breakdown_dimension')
                                ->label('Breakdown Dimension')
                                ->options(SavedSignalReportDefinition::journeyBreakdownDimensionOptions())
                                ->default('path_pair')
                                ->required(),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'journeys')
                        ->columnSpanFull(),

                    Section::make('Content Settings')
                        ->schema([
                            Forms\Components\Select::make('settings.breakdown_dimension')
                                ->label('Breakdown Dimension')
                                ->options(SavedSignalReportDefinition::contentBreakdownDimensionOptions())
                                ->default('path')
                                ->required(),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'content_performance')
                        ->columnSpanFull(),

                    Section::make('Retention Settings')
                        ->schema([
                            Forms\Components\Repeater::make('settings.retention_windows')
                                ->schema([
                                    Forms\Components\TextInput::make('days')
                                        ->label('Days')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),
                                ])
                                ->columnSpanFull()
                                ->default([
                                    ['days' => 7],
                                    ['days' => 30],
                                ])
                                ->addActionLabel('Add Retention Window')
                                ->helperText('Leave empty to use the default 7-day and 30-day retention windows.'),
                        ])
                        ->visible(fn (Get $get): bool => $get('report_type') === 'retention')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('report_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('segment.name')
                    ->label('Segment')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_shared')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_shared'),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('report_type')
                    ->options(SavedSignalReportDefinition::reportTypeOptions()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSavedSignalReports::route('/'),
            'create' => Pages\CreateSavedSignalReport::route('/create'),
            'edit' => Pages\EditSavedSignalReport::route('/{record}/edit'),
        ];
    }
}
