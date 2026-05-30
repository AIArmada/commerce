<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages\CreateAffiliateRank;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages\EditAffiliateRank;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages\ListAffiliateRanks;
use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages\ViewAffiliateRank;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateRankResource extends Resource
{
    protected static ?string $model = AffiliateRank::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Affiliate Ranks';

    protected static ?string $modelLabel = 'Affiliate Rank';

    protected static ?string $pluralModelLabel = 'Affiliate Ranks';

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
        return FilamentPermission::hasAbility('affiliate.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateRank> $query */
        $query = parent::getEloquentQuery();

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

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Rank Details')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('level')
                        ->numeric()
                        ->required()
                        ->minValue(1),

                    Forms\Components\TextInput::make('commission_rate_basis_points')
                        ->label('Commission Rate (basis points)')
                        ->numeric()
                        ->required()
                        ->default(0),
                ])
                ->columns(2),

            Section::make('Qualification Thresholds')
                ->schema([
                    Forms\Components\TextInput::make('min_personal_sales')
                        ->numeric()
                        ->required()
                        ->default(0),

                    Forms\Components\TextInput::make('min_team_sales')
                        ->numeric()
                        ->required()
                        ->default(0),

                    Forms\Components\TextInput::make('min_active_downlines')
                        ->numeric()
                        ->required()
                        ->default(0),
                ])
                ->columns(3),

            Section::make('Overrides & Benefits')
                ->schema([
                    Forms\Components\KeyValue::make('override_rates')
                        ->keyLabel('Depth')
                        ->valueLabel('Rate (basis points)'),

                    Forms\Components\KeyValue::make('benefits')
                        ->keyLabel('Benefit')
                        ->valueLabel('Value'),
                ])
                ->collapsed(),
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
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('level')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_rate_basis_points')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state): string => ((int) $state / 100) . '%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliates_count')
                    ->counts('affiliates')
                    ->label('Affiliates'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
        return $schema->schema([
            Section::make('Rank')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug'),
                    TextEntry::make('level')
                        ->numeric(),
                    TextEntry::make('commission_rate_basis_points')
                        ->label('Commission Rate (basis points)')
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
            'index' => ListAffiliateRanks::route('/'),
            'create' => CreateAffiliateRank::route('/create'),
            'view' => ViewAffiliateRank::route('/{record}'),
            'edit' => EditAffiliateRank::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_ranks', 67);
    }
}
