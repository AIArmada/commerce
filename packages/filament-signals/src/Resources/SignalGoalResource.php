<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;
use AIArmada\FilamentSignals\Support\SignalFormOptionLists;
use AIArmada\Signals\Models\SignalGoal;
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

final class SignalGoalResource extends Resource
{
    protected static ?string $model = SignalGoal::class;

    protected static ?string $modelLabel = 'Goal';

    protected static ?string $pluralModelLabel = 'Goals';

    protected static ?string $navigationLabel = 'Goals';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalGoal>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalGoal::query()->forOwner()->with('trackedProperty');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Goal')
                ->description('Define the event that counts as success, then add optional rules to narrow it down.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Goal name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Example: Shared link produced a signup')
                        ->helperText('Shown to teammates in dashboards, funnels, and reports.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug / internal key')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('shared-link-produced-a-signup')
                        ->helperText('Auto-filled from the name. Edit it if you need a shorter internal key.')
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Website or app')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                TrackedProperty::query()->forOwner()->select('id')
                            ),
                        )
                        ->helperText('Choose which tracked property this goal should watch.')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('goal_type')
                        ->label('Goal type')
                        ->options([
                            'conversion' => 'Conversion',
                            'engagement' => 'Engagement',
                            'revenue' => 'Revenue',
                        ])
                        ->helperText('Use Conversion for success actions, Engagement for softer actions, and Revenue for money-based goals.')
                        ->default('conversion')
                        ->required(),

                    Forms\Components\TextInput::make('event_name')
                        ->label('Event name to count')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Example: affiliate.conversion.recorded')
                        ->datalist(SignalFormOptionLists::eventNames())
                        ->helperText('Choose a common event name or type the exact event you want this goal to count.'),

                    Forms\Components\TextInput::make('event_category')
                        ->label('Event category (optional)')
                        ->maxLength(100)
                        ->placeholder('Example: acquisition')
                        ->datalist(SignalFormOptionLists::eventCategories())
                        ->helperText('Optional. Use this when the event name alone is not specific enough.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Notes')
                        ->rows(3)
                        ->placeholder('Optional context for teammates about what this goal represents.')
                        ->helperText('Optional. Add plain-language context if the goal name is not self-explanatory.')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('conditions')
                        ->label('Extra rules')
                        ->helperText('Add optional rules if this goal should only count a subset of matching events.')
                        ->schema([
                            Forms\Components\TextInput::make('field')
                                ->label('Field to check')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Example: path or properties.checkout.gateway')
                                ->datalist(SignalFormOptionLists::conditionFields())
                                ->helperText('Choose a common field or type your own custom properties.* key.'),
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
                                ->helperText('Use numeric comparisons for amounts like revenue_minor. Use In List when you want to check a comma-separated list.')
                                ->required(),
                            Forms\Components\TextInput::make('value')
                                ->label('Value to match')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Example: signup, telegram, or /majlis'),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(1)
                        ->addActionLabel('Add rule'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Goal name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('goal_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_name')
                    ->label('Event name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_category')
                    ->label('Event category')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Website / app')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Rules')
                    ->formatStateUsing(fn (mixed $state): string => (string) count(is_array($state) ? $state : [])),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No goals yet')
            ->emptyStateDescription('Create your first success event definition so dashboards and funnels can measure it.')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('goal_type')
                    ->label('Type')
                    ->options([
                        'conversion' => 'Conversion',
                        'engagement' => 'Engagement',
                        'revenue' => 'Revenue',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit'),
                DeleteAction::make()
                    ->label('Delete')
                    ->modalHeading('Delete goal?')
                    ->modalDescription('This will remove the saved goal from dashboards, funnels, and reports.')
                    ->successNotificationTitle('Goal deleted'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->modalHeading('Delete goals?')
                        ->modalDescription('This will remove the selected saved goals from dashboards, funnels, and reports.')
                        ->successNotificationTitle('Goals deleted'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalGoals::route('/'),
            'create' => Pages\CreateSignalGoal::route('/create'),
            'edit' => Pages\EditSignalGoal::route('/{record}/edit'),
        ];
    }
}
