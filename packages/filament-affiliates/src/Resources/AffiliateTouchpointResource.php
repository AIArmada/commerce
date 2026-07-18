<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateTouchpointResource\Pages\ListAffiliateTouchpoints;
use AIArmada\FilamentAffiliates\Resources\AffiliateTouchpointResource\Pages\ViewAffiliateTouchpoint;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateTouchpointResource extends Resource
{
    protected static ?string $model = AffiliateTouchpoint::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    protected static ?string $navigationLabel = 'Touchpoints';

    protected static ?string $modelLabel = 'Touchpoint';

    protected static ?string $pluralModelLabel = 'Touchpoints';

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

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateTouchpoint> $query */
        $query = parent::getEloquentQuery()->with(['affiliate', 'attribution']);

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
                Tables\Columns\TextColumn::make('touched_at')
                    ->label('Touched At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject_key')
                    ->label('Subject')
                    ->searchable(),

                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('medium')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('campaign')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('recent')
                    ->query(fn (Builder $query): Builder => $query->where('touched_at', '>=', now()->subDays(7))),
            ])
            ->defaultSort('touched_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Touchpoint')
                ->schema([
                    TextEntry::make('affiliate.code')
                        ->label('Affiliate'),
                    TextEntry::make('subject_key')
                        ->label('Subject'),
                    TextEntry::make('source')
                        ->placeholder('—'),
                    TextEntry::make('medium')
                        ->placeholder('—'),
                    TextEntry::make('campaign')
                        ->placeholder('—'),
                    TextEntry::make('touched_at')
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
            'index' => ListAffiliateTouchpoints::route('/'),
            'view' => ViewAffiliateTouchpoint::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_touchpoints', 66);
    }
}
