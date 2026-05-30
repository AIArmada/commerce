<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource\Pages\ListAffiliateNetworks;
use AIArmada\FilamentAffiliates\Resources\AffiliateNetworkResource\Pages\ViewAffiliateNetwork;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateNetworkResource extends Resource
{
    protected static ?string $model = AffiliateNetwork::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationLabel = 'Affiliate Network';

    protected static ?string $modelLabel = 'Network Link';

    protected static ?string $pluralModelLabel = 'Network Links';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('affiliate.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateNetwork> $query */
        $query = parent::getEloquentQuery()->with(['ancestor', 'descendant']);

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery;
        }

        $scopedQuery = $query->forOwner();

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $scopedQuery;

        return $modelQuery;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ancestor.code')
                    ->label('Ancestor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('descendant.code')
                    ->label('Descendant')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('depth')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('depth');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Network Link')
                ->schema([
                    TextEntry::make('ancestor.code')
                        ->label('Ancestor'),
                    TextEntry::make('descendant.code')
                        ->label('Descendant'),
                    TextEntry::make('depth')
                        ->numeric(),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateNetworks::route('/'),
            'view' => ViewAffiliateNetwork::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_network', 68);
    }
}
