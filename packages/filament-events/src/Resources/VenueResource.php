<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\Venue;
use AIArmada\FilamentEvents\Resources\VenueResource\Pages;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.venues', 4);
    }

    /**
     * @return Builder<Venue>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Venue> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->withCount('occurrences');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('Venue')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug($state ?? ''))),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('contact_name')
                                    ->maxLength(255),

                                TextInput::make('contact_email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('contact_phone')
                                    ->tel()
                                    ->maxLength(255),

                                Textarea::make('notes')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(['lg' => 2]),

                        Section::make('Location')
                            ->schema([
                                TextInput::make('line1')
                                    ->maxLength(255),

                                TextInput::make('line2')
                                    ->maxLength(255),

                                TextInput::make('city')
                                    ->maxLength(255),

                                TextInput::make('state')
                                    ->maxLength(255),

                                TextInput::make('postcode')
                                    ->maxLength(255),

                                TextInput::make('country')
                                    ->required()
                                    ->maxLength(2)
                                    ->default('MY'),

                                TextInput::make('timezone')
                                    ->maxLength(64)
                                    ->default('Asia/Kuala_Lumpur'),

                                KeyValue::make('metadata'),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Venue $record): string => $record->slug),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->placeholder('No city'),

                Tables\Columns\TextColumn::make('country')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('timezone')
                    ->placeholder('Not set')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('occurrences_count')
                    ->label('Runs')
                    ->counts('occurrences')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Venue')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('contact_name')
                            ->placeholder('Not set'),
                        TextEntry::make('contact_email')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('contact_phone')
                            ->copyable()
                            ->placeholder('Not set'),
                        TextEntry::make('timezone')
                            ->placeholder('Not set'),
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])
                    ->columns(3),

                Section::make('Location')
                    ->schema([
                        TextEntry::make('line1')
                            ->placeholder('Not set'),
                        TextEntry::make('line2')
                            ->placeholder('Not set'),
                        TextEntry::make('city')
                            ->placeholder('Not set'),
                        TextEntry::make('state')
                            ->placeholder('Not set'),
                        TextEntry::make('postcode')
                            ->placeholder('Not set'),
                        TextEntry::make('country'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'view' => Pages\ViewVenue::route('/{record}'),
            'edit' => Pages\EditVenue::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'city', 'state', 'country'];
    }
}
