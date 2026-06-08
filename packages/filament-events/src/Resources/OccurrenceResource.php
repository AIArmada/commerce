<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Data\OccurrenceDetailData;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubLocation;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Support\EventAddressRegistry;
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
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

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
            ->with(['event', 'address', 'subLocation', 'product', 'variant'])
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

            Select::make('participation_mode')
                ->label('Participation Mode')
                ->options(static::participationModeOptions())
                ->required()
                ->default(OccurrenceParticipationMode::RegistrationRequired->value),

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
                            Select::make('address_type')
                                ->label('Address Type')
                                ->options(static::addressTypeOptions())
                                ->placeholder('No address')
                                ->live()
                                ->afterStateUpdated(static function (Set $set): void {
                                    $set('address_id', null);
                                }),

                            Select::make('address_id')
                                ->label('Address')
                                ->required(static fn (Get $get): bool => is_string($get('address_type')))
                                ->searchable()
                                ->placeholder('Select address')
                                ->getSearchResultsUsing(static function (string $search, Get $get): array {
                                    $addressType = $get('address_type');

                                    if (! is_string($addressType) || $addressType === '') {
                                        return [];
                                    }

                                    return EventAddressRegistry::searchResults($addressType, $search);
                                })
                                ->getOptionLabelUsing(static function (mixed $value, Get $get): ?string {
                                    $addressType = $get('address_type');

                                    if (! is_string($addressType) || $addressType === '' || $value === null) {
                                        return null;
                                    }

                                    return EventAddressRegistry::optionLabel($addressType, $value);
                                })
                                ->hidden(static fn (Get $get): bool => ! is_string($get('address_type')))
                                ->dehydrated(static fn (Get $get): bool => is_string($get('address_type'))),

                            Select::make('sub_location_id')
                                ->label('Sub-location')
                                ->placeholder('No sub-location')
                                ->relationship(
                                    name: 'subLocation',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: true),
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

                    Section::make('Registration Policy')
                        ->schema([
                            Toggle::make('waitlist_enabled')
                                ->label('Enable Waitlist'),

                            Toggle::make('approval_required')
                                ->label('Approval Required'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),

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

                Tables\Columns\TextColumn::make('participation_mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn (OccurrenceParticipationMode $state): string => $state->label())
                    ->color(fn (OccurrenceParticipationMode $state): string => $state->color())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('approval_required')
                    ->label('Approval')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Open')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('waitlist_enabled')
                    ->label('Waitlist')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('location_label')
                    ->label('Location')
                    ->placeholder('No address')
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

                Tables\Filters\SelectFilter::make('participation_mode')
                    ->label('Participation Mode')
                    ->options(static::participationModeOptions()),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(static fn (): array => OwnerUiScope::apply(Event::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),

                Tables\Filters\SelectFilter::make('address_type')
                    ->label('Address Type')
                    ->options(static::addressTypeOptions()),

                Tables\Filters\SelectFilter::make('sub_location_id')
                    ->label('Sub-location')
                    ->options(static::subLocationOptions()),

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
                        TextEntry::make('participation_mode')
                            ->label('Participation Mode')
                            ->badge()
                            ->formatStateUsing(fn (OccurrenceParticipationMode $state): string => $state->label())
                            ->color(fn (OccurrenceParticipationMode $state): string => $state->color()),
                        TextEntry::make('starts_at')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('ends_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not set'),
                        TextEntry::make('timezone'),
                        TextEntry::make('location_label')
                            ->label('Location')
                            ->placeholder('No address'),
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

                Section::make('Registration Policy')
                    ->schema([
                        TextEntry::make('waitlist_enabled')
                            ->label('Waitlist')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                            ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                        TextEntry::make('approval_required')
                            ->label('Approval')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Required' : 'Open')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                    ])
                    ->columns(2),

                Section::make('Read Model Snapshot')
                    ->description('Projections generated by the package EventQueryService from the OccurrenceDetailData DTO.')
                    ->schema([
                        TextEntry::make('snapshot.addressLines')
                            ->label('Address Lines')
                            ->state(fn (Occurrence $record): string => self::formatStringList(self::snapshot($record)->addressLines))
                            ->placeholder('No address lines')
                            ->columnSpanFull(),

                        TextEntry::make('snapshot.coordinates')
                            ->label('Coordinates')
                            ->state(fn (Occurrence $record): string => self::formatCoordinates(self::snapshot($record)))
                            ->placeholder('No coordinates')
                            ->columnSpanFull(),

                        TextEntry::make('snapshot.agendaItems')
                            ->label('Agenda Items')
                            ->state(function (Occurrence $record): string {
                                $items = self::snapshot($record)->agendaItems;

                                return implode("\n", array_map(
                                    static fn ($item): string => sprintf(
                                        '%s (%s — %s)',
                                        (string) ($item->title ?? $item->segmentKey),
                                        (string) ($item->startsAt ?? '—'),
                                        (string) ($item->endsAt ?? '—'),
                                    ),
                                    $items,
                                ));
                            })
                            ->placeholder('No agenda items')
                            ->columnSpanFull(),

                        TextEntry::make('snapshot.references')
                            ->label('Reference Materials')
                            ->state(fn (Occurrence $record): string => self::formatJsonState(self::snapshot($record)->references))
                            ->placeholder('No references')
                            ->columnSpanFull(),

                        TextEntry::make('snapshot.metadata')
                            ->label('Metadata')
                            ->state(fn (Occurrence $record): string => self::formatJsonState(self::snapshot($record)->metadata))
                            ->placeholder('No metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
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
    public static function addressTypeOptions(): array
    {
        return EventAddressRegistry::labels();
    }

    /**
     * @return array<string, string>
     */
    public static function subLocationOptions(): array
    {
        return OwnerUiScope::apply(EventSubLocation::query(), includeGlobal: true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Resolve the package-native OccurrenceDetailData read model for an
     * occurrence so admin views read the same projection that API adapters,
     * search payloads, and downstream tooling consume.
     */
    public static function snapshot(Occurrence $occurrence): OccurrenceDetailData
    {
        return app(EventQueryService::class)->occurrence($occurrence);
    }

    /**
     * @param  array<int, string>  $items
     */
    private static function formatStringList(array $items): string
    {
        $filtered = array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_string($item) && mb_trim($item) !== '',
        ));

        return $filtered === [] ? '' : implode(', ', $filtered);
    }

    private static function formatCoordinates(OccurrenceDetailData $dto): string
    {
        $lat = $dto->addressLatitude;
        $lng = $dto->addressLongitude;

        if ($lat === null && $lng === null) {
            return '';
        }

        return mb_trim(sprintf('%s, %s', (string) ($lat ?? '—'), (string) ($lng ?? '—')));
    }

    private static function formatJsonState(mixed $state): string
    {
        if (! is_array($state)) {
            return '';
        }

        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeMutationData(array $data): array
    {
        $data['address_type'] = static::normalizeAddressType($data['address_type'] ?? null);
        $data['address_id'] = static::normalizeAddressId($data['address_type'], $data['address_id'] ?? null);
        $data['sub_location_id'] = static::normalizeSubLocationId($data['sub_location_id'] ?? null);

        return $data;
    }

    private static function normalizeAddressType(mixed $addressType): ?string
    {
        if (! is_string($addressType)) {
            return null;
        }

        $addressType = mb_trim($addressType);

        if ($addressType === '') {
            return null;
        }

        if (! array_key_exists($addressType, static::addressTypeOptions())) {
            throw ValidationException::withMessages([
                'address_type' => 'The selected address type is invalid.',
            ]);
        }

        return $addressType;
    }

    private static function normalizeAddressId(?string $addressType, mixed $addressId): ?string
    {
        if ($addressType === null) {
            if ($addressId !== null && $addressId !== '') {
                throw ValidationException::withMessages([
                    'address_id' => 'The selected address is invalid.',
                ]);
            }

            return null;
        }

        if (! is_scalar($addressId)) {
            throw ValidationException::withMessages([
                'address_id' => 'The selected address is invalid.',
            ]);
        }

        try {
            $address = EventAddressRegistry::resolveRecord($addressType, $addressId);
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'address_id' => 'The selected address is not accessible in the current owner scope.',
            ]);
        }

        if (! $address instanceof Model) {
            throw ValidationException::withMessages([
                'address_id' => 'The selected address is invalid.',
            ]);
        }

        return (string) $address->getKey();
    }

    private static function normalizeSubLocationId(mixed $subLocationId): ?string
    {
        if ($subLocationId === null || $subLocationId === '') {
            return null;
        }

        if (! is_scalar($subLocationId)) {
            throw ValidationException::withMessages([
                'sub_location_id' => 'The selected sub-location is invalid.',
            ]);
        }

        try {
            $subLocation = OwnerWriteGuard::findOrFailForOwner(
                EventSubLocation::class,
                (string) $subLocationId,
                includeGlobal: true,
                message: 'The selected sub-location is not accessible in the current owner scope.',
            );
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'sub_location_id' => 'The selected sub-location is not accessible in the current owner scope.',
            ]);
        }

        if (! $subLocation instanceof EventSubLocation) {
            throw ValidationException::withMessages([
                'sub_location_id' => 'The selected sub-location is invalid.',
            ]);
        }

        return (string) $subLocation->getKey();
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

    /**
     * @return array<string, string>
     */
    public static function participationModeOptions(): array
    {
        return collect(OccurrenceParticipationMode::cases())
            ->mapWithKeys(fn (OccurrenceParticipationMode $mode): array => [$mode->value => $mode->label()])
            ->all();
    }
}
