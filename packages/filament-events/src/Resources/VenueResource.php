<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Events\Models\Venue;
use AIArmada\FilamentEvents\Actions\Exporter\VenueExporter;
use AIArmada\FilamentEvents\Actions\Importer\VenueImporter;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.venue');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->with('addresses'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('venue_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('city')
                    ->state(static fn (Venue $record): ?string => static::address($record)?->city),
                Tables\Columns\TextColumn::make('state')
                    ->state(static fn (Venue $record): ?string => static::address($record)?->state),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->state(static fn (Venue $record): ?string => static::address($record)?->countryCode),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ((string) $state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'closed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('venue_type')
                    ->options([
                        'physical' => 'Physical',
                        'online' => 'Online',
                        'virtual' => 'Virtual',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'closed' => 'Closed',
                    ]),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(VenueImporter::class)
                    ->label('Import Venues'),
                ExportAction::make()
                    ->exporter(VenueExporter::class)
                    ->label('Export Venues'),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('venue_type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('visibility')->badge(),
                    ])->columns(2),
                Section::make('Address')
                    ->schema([
                        TextEntry::make('line1')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->line1),
                        TextEntry::make('line2')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->line2),
                        TextEntry::make('city')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->city),
                        TextEntry::make('state')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->state),
                        TextEntry::make('postcode')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->postcode),
                        TextEntry::make('country_code')
                            ->label('Country')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->countryCode),
                    ])->columns(2),
                Section::make('Coordinates / Maps')
                    ->schema([
                        TextEntry::make('latitude')
                            ->state(static fn (Venue $record): ?float => static::address($record)?->latitude),
                        TextEntry::make('longitude')
                            ->state(static fn (Venue $record): ?float => static::address($record)?->longitude),
                        TextEntry::make('provider_place_id')
                            ->label('Provider Place ID')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->providerPlaceId),
                        TextEntry::make('google_maps_url')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->googleMapsUrl),
                        TextEntry::make('waze_url')
                            ->state(static fn (Venue $record): ?string => static::address($record)?->wazeUrl),
                    ])->columns(2),
                Section::make('Contact')
                    ->schema([
                        TextEntry::make('phone'),
                        TextEntry::make('email'),
                        TextEntry::make('website_url'),
                        TextEntry::make('directions')
                            ->state(static fn (Venue $record): ?string => static::directions($record)),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => VenueResource\Pages\ListVenues::route('/'),
            'view' => VenueResource\Pages\ViewVenue::route('/{record}'),
        ];
    }

    private static function address(Venue $venue): ?AddressData
    {
        return $venue->getPrimaryAddressData();
    }

    private static function directions(Venue $venue): ?string
    {
        $directions = static::address($venue)?->metadata['directions'] ?? null;

        return is_string($directions) ? $directions : null;
    }
}
