<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Venue;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\Pages;
use AIArmada\FilamentEvents\Resources\OccurrenceResource\RelationManagers;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class OccurrenceResource extends Resource
{
    protected static ?string $model = Occurrence::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.occurrences', 3);
    }

    /**
     * @return Builder<Occurrence>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Occurrence> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->with(['event', 'venue', 'product', 'variant'])
            ->withCount('registrations');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereIn('status', [OccurrenceStatus::Scheduled, OccurrenceStatus::Live])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::formSchema());
    }

    /**
     * @return array<int, mixed>
     */
    public static function formSchema(bool $includeEventField = true): array
    {
        $identityFields = [
            TextInput::make('name')
                ->maxLength(255),

            Select::make('status')
                ->options(static::statusOptions())
                ->required()
                ->default(OccurrenceStatus::Draft->value),

            DateTimePicker::make('starts_at')
                ->required(),

            DateTimePicker::make('ends_at')
                ->after('starts_at'),

            TextInput::make('timezone')
                ->required()
                ->maxLength(64)
                ->default('Asia/Kuala_Lumpur'),
        ];

        if ($includeEventField) {
            array_unshift(
                $identityFields,
                Select::make('event_id')
                    ->label('Event')
                    ->relationship(
                        name: 'event',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                    )
                    ->required()
                    ->searchable()
                    ->preload(),
            );
        }

        return [
            Grid::make(3)
                ->schema([
                    Section::make('Occurrence')
                        ->schema($identityFields)
                        ->columns(2)
                        ->columnSpan(['lg' => 2]),

                    Section::make('Location and Commerce')
                        ->schema([
                            Select::make('venue_id')
                                ->label('Venue')
                                ->relationship(
                                    name: 'venue',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('product_id')
                                ->label('Product')
                                ->relationship(
                                    name: 'product',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('variant_id')
                                ->label('Variant')
                                ->relationship(
                                    name: 'variant',
                                    titleAttribute: 'sku',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                )
                                ->searchable()
                                ->preload(),

                            KeyValue::make('metadata'),
                        ])
                        ->columnSpan(['lg' => 1]),

                    Section::make('Registration Window')
                        ->schema([
                            DateTimePicker::make('registration_opens_at'),
                            DateTimePicker::make('registration_closes_at')
                                ->after('registration_opens_at'),
                            DateTimePicker::make('check_in_opens_at'),
                            DateTimePicker::make('check_in_closes_at')
                                ->after('check_in_opens_at'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->placeholder('Unnamed run')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OccurrenceStatus $state): string => $state->label())
                    ->color(fn (OccurrenceStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('venue.name')
                    ->label('Venue')
                    ->placeholder('No venue')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations')
                    ->sortable(),

                Tables\Columns\TextColumn::make('timezone')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(static fn (): array => OwnerUiScope::apply(Event::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                Tables\Filters\SelectFilter::make('venue_id')
                    ->label('Venue')
                    ->options(static fn (): array => OwnerUiScope::apply(Venue::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                Tables\Filters\Filter::make('starts_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('starts_at', '<=', $date));
                    }),
            ])
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
                Section::make('Occurrence')
                    ->schema([
                        TextEntry::make('event.name')
                            ->label('Event'),
                        TextEntry::make('name')
                            ->placeholder('Unnamed run'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (OccurrenceStatus $state): string => $state->label())
                            ->color(fn (OccurrenceStatus $state): string => $state->color()),
                        TextEntry::make('starts_at')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('ends_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not set'),
                        TextEntry::make('timezone'),
                        TextEntry::make('venue.name')
                            ->label('Venue')
                            ->placeholder('No venue'),
                        TextEntry::make('registrations_count')
                            ->label('Registrations')
                            ->state(fn (Occurrence $record): int => $record->registrations()->count()),
                    ])
                    ->columns(4),

                Section::make('Registration Window')
                    ->schema([
                        TextEntry::make('registration_opens_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                        TextEntry::make('registration_closes_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                        TextEntry::make('check_in_opens_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                        TextEntry::make('check_in_closes_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                    ])
                    ->columns(4),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOccurrences::route('/'),
            'create' => Pages\CreateOccurrence::route('/create'),
            'view' => Pages\ViewOccurrence::route('/{record}'),
            'edit' => Pages\EditOccurrence::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(OccurrenceStatus::cases())
            ->mapWithKeys(fn (OccurrenceStatus $status): array => [$status->value => $status->label()])
            ->all();
    }
}
