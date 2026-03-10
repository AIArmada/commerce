<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalSegmentResource\Pages;
use AIArmada\Signals\Models\SignalSegment;
use BackedEnum;
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

final class SignalSegmentResource extends Resource
{
    protected static ?string $model = SignalSegment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalSegment>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalSegment::query()->forOwner();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Segment')
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

                    Forms\Components\Select::make('match_type')
                        ->options([
                            'all' => 'Match All Conditions',
                            'any' => 'Match Any Condition',
                        ])
                        ->helperText('Only all and any are accepted. Invalid values are rejected when saving.')
                        ->default('all')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('conditions')
                        ->schema([
                            Forms\Components\TextInput::make('field')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('path or properties.checkout.gateway')
                                ->helperText('Supported fields: path, url, source, medium, campaign, referrer, currency, event_name, event_category, revenue_minor, and properties.* JSON keys. Invalid fields are rejected when saving.'),
                            Forms\Components\Select::make('operator')
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
                                    'in' => 'In',
                                ])
                                ->helperText('Numeric comparisons are supported for revenue_minor and typed numeric properties.* values.')
                                ->required(),
                            Forms\Components\TextInput::make('value')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(1)
                        ->addActionLabel('Add Condition'),
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
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('match_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Conditions')
                    ->formatStateUsing(fn (mixed $state): string => (string) count(is_array($state) ? $state : [])),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('match_type')
                    ->options([
                        'all' => 'Match All',
                        'any' => 'Match Any',
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalSegments::route('/'),
            'create' => Pages\CreateSignalSegment::route('/create'),
            'edit' => Pages\EditSignalSegment::route('/{record}/edit'),
        ];
    }
}
