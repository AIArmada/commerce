<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

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
use UnitEnum;

final class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('venue_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\TextColumn::make('state'),
                Tables\Columns\TextColumn::make('country'),
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
                Tables\Filters\SelectFilter::make('country'),
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
                        TextEntry::make('line1'),
                        TextEntry::make('line2'),
                        TextEntry::make('city'),
                        TextEntry::make('state'),
                        TextEntry::make('postcode'),
                        TextEntry::make('country'),
                    ])->columns(2),
                Section::make('Coordinates / Maps')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                        TextEntry::make('google_place_id'),
                        TextEntry::make('google_maps_url'),
                        TextEntry::make('waze_url'),
                        TextEntry::make('map_url'),
                    ])->columns(2),
                Section::make('Contact')
                    ->schema([
                        TextEntry::make('phone'),
                        TextEntry::make('email'),
                        TextEntry::make('website_url'),
                        TextEntry::make('directions'),
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
}
