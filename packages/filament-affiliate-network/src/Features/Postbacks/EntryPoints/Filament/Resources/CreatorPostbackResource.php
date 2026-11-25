<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources;

use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages\CreateCreatorPostback;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages\EditCreatorPostback;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Pages\ListCreatorPostbacks;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\RelationManagers\DeliveriesRelationManager;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Schemas\CreatorPostbackForm;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource\Tables\CreatorPostbacksTable;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class CreatorPostbackResource extends Resource
{
    protected static ?string $model = CreatorPostbackEndpoint::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Creator Postbacks';

    protected static ?string $modelLabel = 'Creator Postback';

    protected static ?string $pluralModelLabel = 'Creator Postbacks';

    public static function canViewAny(): bool
    {
        return NetworkAdminAccess::allows();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 5;
    }

    public static function form(Schema $schema): Schema
    {
        return CreatorPostbackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreatorPostbacksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreatorPostbacks::route('/'),
            'create' => CreateCreatorPostback::route('/create'),
            'edit' => EditCreatorPostback::route('/{record}/edit'),
        ];
    }
}
