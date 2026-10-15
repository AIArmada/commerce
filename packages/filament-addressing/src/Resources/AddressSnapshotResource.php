<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Actions\BuildAddressNavigationLinksAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource\Pages\ListAddressSnapshots;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource\Pages\ViewAddressSnapshot;
use AIArmada\FilamentAddressing\Tables\AddressSnapshotTable;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AddressSnapshotResource extends Resource
{
    protected static ?string $model = AddressSnapshot::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.snapshots', parent::getNavigationIcon());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80) + 3;
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.snapshots.model', AddressSnapshot::class);
    }

    /**
     * @return Builder<AddressSnapshot>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return AddressSnapshotTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Snapshot')
                    ->schema([
                        TextEntry::make('snapshotable_type')->label('Source Type'),
                        TextEntry::make('snapshotable_id')->label('Source ID'),
                        TextEntry::make('reason')->badge(),
                        TextEntry::make('formatted_address'),
                    ])->columns(2),
                Section::make('Address Details')
                    ->schema([
                        TextEntry::make('label'),
                        TextEntry::make('line1'),
                        TextEntry::make('line2'),
                        TextEntry::make('line3'),
                        TextEntry::make('city'),
                        TextEntry::make('state'),
                        TextEntry::make('postcode'),
                        TextEntry::make('country'),
                        TextEntry::make('country_code')->label('Country Code'),
                    ])->columns(3),
                Section::make('Coordinates')
                    ->schema([
                        TextEntry::make('latitude'),
                        TextEntry::make('longitude'),
                    ])->columns(2),
                Section::make('Navigation and source data')
                    ->schema([
                        TextEntry::make('google_maps_url')
                            ->label('Google Maps')
                            ->state(fn (AddressSnapshot $record): ?string => app(BuildAddressNavigationLinksAction::class)
                                ->execute(AddressData::from($record->attributesToArray()))['google_maps_url'])
                            ->url(fn (AddressSnapshot $record): ?string => app(BuildAddressNavigationLinksAction::class)
                                ->execute(AddressData::from($record->attributesToArray()))['google_maps_url'])
                            ->openUrlInNewTab(),
                        TextEntry::make('waze_url')
                            ->label('Waze')
                            ->state(fn (AddressSnapshot $record): ?string => app(BuildAddressNavigationLinksAction::class)
                                ->execute(AddressData::from($record->attributesToArray()))['waze_url'])
                            ->url(fn (AddressSnapshot $record): ?string => app(BuildAddressNavigationLinksAction::class)
                                ->execute(AddressData::from($record->attributesToArray()))['waze_url'])
                            ->openUrlInNewTab(),
                        TextEntry::make('google_place_id'),
                        TextEntry::make('google_feature_id'),
                        TextEntry::make('google_cid'),
                        TextEntry::make('google_entity_id'),
                        TextEntry::make('navigation_links')->formatStateUsing(fn (mixed $state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' : '—'),
                        TextEntry::make('metadata')->formatStateUsing(fn (mixed $state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' : '—'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAddressSnapshots::route('/'),
            'view' => ViewAddressSnapshot::route('/{record}'),
        ];
    }
}
