<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Pages;
use AIArmada\FilamentSignals\Support\SignalFormOptionLists;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\TrackedProperty;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class SignalAlertRuleResource extends Resource
{
    protected static ?string $model = SignalAlertRule::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 33;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalAlertRule>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalAlertRule::query()->forOwner()->with('trackedProperty');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.alert_rules', 33);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Alert Rule')
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
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('metric_key')
                        ->options(function (): array {
                            $options = [
                                'events' => 'Events',
                                'event_count' => 'Event Count',
                                'page_views' => 'Page Views',
                                'conversions' => 'Conversions',
                                'conversion_rate' => 'Conversion Rate (%)',
                                'property_sum:cart_total_minor' => 'Cart Total Sum',
                                'property_avg:cart_total_minor' => 'Average Cart Total',
                                'property_max:cart_total_minor' => 'Max Cart Total',
                            ];
                            if (config('signals.features.monetary.enabled', true)) {
                                $options['revenue_minor'] = 'Revenue (Minor)';
                            }

                            return $options;
                        })
                        ->required(),

                    Forms\Components\Select::make('operator')
                        ->options([
                            '>' => 'Greater Than',
                            '>=' => 'Greater Than or Equal',
                            '<' => 'Less Than',
                            '<=' => 'Less Than or Equal',
                            '=' => 'Equals',
                            '!=' => 'Not Equals',
                        ])
                        ->default('>=')
                        ->required(),

                    Forms\Components\TextInput::make('threshold')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('timeframe_minutes')
                        ->numeric()
                        ->default(60)
                        ->required(),

                    Forms\Components\TextInput::make('cooldown_minutes')
                        ->numeric()
                        ->default(60)
                        ->required(),

                    Forms\Components\Select::make('severity')
                        ->options([
                            'info' => 'Info',
                            'warning' => 'Warning',
                            'critical' => 'Critical',
                        ])
                        ->default('warning')
                        ->required(),

                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Event filters')
                ->description('Filter by event name, category, and optional event properties. These filters are generic and work for any package that records Signals events.')
                ->schema([
                    Forms\Components\TagsInput::make('event_filters.event_names')
                        ->label('Event names')
                        ->suggestions(SignalFormOptionLists::eventNames())
                        ->placeholder('cart.abandoned'),

                    Forms\Components\TagsInput::make('event_filters.event_categories')
                        ->label('Event categories')
                        ->suggestions(SignalFormOptionLists::eventCategories())
                        ->placeholder('cart'),

                    Forms\Components\Repeater::make('event_filters.properties')
                        ->label('Property conditions')
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->required()
                                ->placeholder('cart_total_minor'),
                            Forms\Components\Select::make('operator')
                                ->options([
                                    'eq' => 'Equals',
                                    'not_eq' => 'Not equals',
                                    '>' => 'Greater Than',
                                    '>=' => 'Greater Than or Equal',
                                    '<' => 'Less Than',
                                    '<=' => 'Less Than or Equal',
                                    'contains' => 'Contains',
                                ])
                                ->default('eq')
                                ->required(),
                            Forms\Components\TextInput::make('value')
                                ->required(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Notification channels')
                ->description('Database logs are always created. Additional channels use named destinations from signals config by default.')
                ->schema([
                    Forms\Components\CheckboxList::make('channels')
                        ->options([
                            'database' => 'Database',
                            'email' => 'Email',
                            'webhook' => 'Webhook',
                            'slack' => 'Slack',
                        ])
                        ->columns(4)
                        ->default(['database']),

                    Forms\Components\TagsInput::make('destination_keys')
                        ->label('Destination keys')
                        ->placeholder('ops'),

                    Forms\Components\KeyValue::make('inline_destinations')
                        ->label('Inline destinations')
                        ->helperText('Only used when signals.features.alerts.allow_inline_destinations is enabled.')
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
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Property')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('metric_key')
                    ->badge(),
                Tables\Columns\TextColumn::make('event_filters.event_names')
                    ->label('Events')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('channels')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('threshold')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('timeframe_minutes')
                    ->label('Window')
                    ->suffix(' min'),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('logs_count')
                    ->counts('logs')
                    ->label('Alerts'),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListSignalAlertRules::route('/'),
            'create' => Pages\CreateSignalAlertRule::route('/create'),
            'edit' => Pages\EditSignalAlertRule::route('/{record}/edit'),
        ];
    }
}
