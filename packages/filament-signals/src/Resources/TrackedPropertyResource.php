<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Pages;
use AIArmada\Signals\Models\TrackedProperty;
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

final class TrackedPropertyResource extends Resource
{
    protected static ?string $model = TrackedProperty::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rss';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<TrackedProperty>
     */
    public static function getEloquentQuery(): Builder
    {
        return TrackedProperty::query()->forOwner();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Property')
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

                    Forms\Components\TextInput::make('domain')
                        ->maxLength(255),

                    Forms\Components\Select::make('type')
                        ->options([
                            'website' => 'Website',
                            'storefront' => 'Storefront',
                            'app' => 'App',
                        ])
                        ->default('website')
                        ->required(),

                    Forms\Components\TextInput::make('timezone')
                        ->default(config('signals.defaults.timezone', 'UTC'))
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('currency')
                        ->default(config('signals.defaults.currency', 'MYR'))
                        ->required()
                        ->length(3),

                    Forms\Components\TextInput::make('write_key')
                        ->required()
                        ->default(Str::random(40))
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText('Use this key from your tracker or ingestion client.'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
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
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('write_key')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListTrackedProperties::route('/'),
            'create' => Pages\CreateTrackedProperty::route('/create'),
            'edit' => Pages\EditTrackedProperty::route('/{record}/edit'),
        ];
    }
}
