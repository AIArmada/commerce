<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages;
use AIArmada\Signals\Models\SignalInteractionRule;
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

final class SignalInteractionRuleResource extends Resource
{
    protected static ?string $model = SignalInteractionRule::class;

    protected static ?string $modelLabel = 'Interaction Rule';

    protected static ?string $pluralModelLabel = 'Interaction Rules';

    protected static ?string $navigationLabel = 'Interaction Rules';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 35;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalInteractionRule>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalInteractionRule::query()->forOwner()->with('trackedProperty');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.interaction_rules', 35);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Interaction rule')
                ->description('Select which page interaction to track, then map it to a Signals event.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Rule name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Hero CTA click')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug / internal key')
                        ->required()
                        ->maxLength(255)
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
                        ->helperText('Leave empty only if you want this rule to apply across all tracked properties in this owner scope.')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('trigger_type')
                        ->label('Interaction type')
                        ->options([
                            'click' => 'Click',
                            'accordion' => 'Accordion toggle',
                            'media' => 'Audio / Video',
                            'youtube' => 'YouTube embed click',
                        ])
                        ->required()
                        ->default('click')
                        ->helperText('Use media for audio/video play/pause/ended hooks. Use youtube for embed wrapper click tracking.'),

                    Forms\Components\TextInput::make('event_name')
                        ->label('Event name')
                        ->required()
                        ->maxLength(255)
                        ->default('ui.click')
                        ->placeholder('ui.click'),

                    Forms\Components\TextInput::make('event_category')
                        ->label('Event category')
                        ->maxLength(100)
                        ->default('engagement')
                        ->placeholder('engagement'),

                    Forms\Components\TextInput::make('selector')
                        ->label('CSS selector')
                        ->maxLength(255)
                        ->placeholder('.hero-cta, [data-track-cta]')
                        ->helperText('For media interactions, leave blank to auto-match native audio/video tags.'),

                    Forms\Components\TextInput::make('page_pattern')
                        ->label('Path pattern (optional)')
                        ->maxLength(255)
                        ->placeholder('/pricing*')
                        ->helperText('Wildcard * is supported. Empty means all paths.'),

                    Forms\Components\KeyValue::make('settings')
                        ->label('Advanced settings')
                        ->helperText('Optional. Example: once_per_session => true')
                        ->reorderable(false)
                        ->addActionLabel('Add setting')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Notes')
                        ->rows(3)
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
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('trigger_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_name')
                    ->label('Event')
                    ->searchable(),
                Tables\Columns\TextColumn::make('settings.scanner_confidence')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(static fn (mixed $state): string => is_numeric($state) ? ((string) ((int) $state) . '%') : '—')
                    ->color(static function (mixed $state): string {
                        if (! is_numeric($state)) {
                            return 'gray';
                        }

                        $score = (int) $state;

                        return match (true) {
                            $score >= 85 => 'success',
                            $score >= 70 => 'warning',
                            default => 'gray',
                        };
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('selector')
                    ->label('Selector')
                    ->wrap()
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('page_pattern')
                    ->label('Path pattern')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('trackedProperty.name')
                    ->label('Website / app')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->paginated([10])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('trigger_type')
                    ->label('Interaction type')
                    ->options([
                        'click' => 'Click',
                        'accordion' => 'Accordion toggle',
                        'media' => 'Audio / Video',
                        'youtube' => 'YouTube embed click',
                    ]),
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
            'index' => Pages\ListSignalInteractionRules::route('/'),
            'create' => Pages\CreateSignalInteractionRule::route('/create'),
            'edit' => Pages\EditSignalInteractionRule::route('/{record}/edit'),
        ];
    }
}
