<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource\Pages\ListAffiliateRankHistories;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankHistoryResource\Pages\ViewAffiliateRankHistory;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateRankHistoryResource extends Resource
{
    protected static ?string $model = AffiliateRankHistory::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Rank History';

    protected static ?string $modelLabel = 'Rank History';

    protected static ?string $pluralModelLabel = 'Rank History';

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
        /** @var Builder<AffiliateRankHistory> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $query;

        return $modelQuery;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable(),

                TextColumn::make('fromRank.name')
                    ->label('From Rank')
                    ->placeholder('—'),

                TextColumn::make('toRank.name')
                    ->label('To Rank')
                    ->placeholder('—'),

                TextColumn::make('reason')
                    ->badge(),

                TextColumn::make('qualified_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Rank Change')
                ->schema([
                    TextEntry::make('affiliate.name')
                        ->label('Affiliate'),
                    TextEntry::make('fromRank.name')
                        ->label('From Rank')
                        ->placeholder('—'),
                    TextEntry::make('toRank.name')
                        ->label('To Rank')
                        ->placeholder('—'),
                    TextEntry::make('reason')
                        ->badge(),
                    TextEntry::make('qualified_at')
                        ->dateTime(),
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
            'index' => ListAffiliateRankHistories::route('/'),
            'view' => ViewAffiliateRankHistory::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_rank_histories', 70);
    }
}
