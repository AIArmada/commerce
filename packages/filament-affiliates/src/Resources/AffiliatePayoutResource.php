<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\CreateAffiliatePayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\ListAffiliatePayouts;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\ViewAffiliatePayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers\ConversionsRelationManager;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers\PayoutEventsRelationManager;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliatePayoutResource extends Resource
{
    protected static ?string $model = AffiliatePayout::class;

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliatePayout> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery->whereHasMorph('payee', [Affiliate::class], static fn (Builder $affiliateQuery): Builder => $affiliateQuery->whereCommercialPurpose());
        }

        $scopedQuery = $query->forOwner()
            ->whereHasMorph('payee', [Affiliate::class], static fn (Builder $affiliateQuery): Builder => $affiliateQuery->whereCommercialPurpose());

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $scopedQuery;

        return $modelQuery;
    }

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Affiliate Payouts';

    protected static ?string $modelLabel = 'Payout';

    protected static ?string $pluralModelLabel = 'Payouts';

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAnyAbility(['affiliates.payout.create', 'affiliate.payout', 'affiliates.payout.update']);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Payout Details')
                ->schema([
                    Forms\Components\Select::make('affiliate_id')
                        ->label('Affiliate')
                        ->getSearchResultsUsing(static fn (string $search): array => CreateAffiliatePayout::affiliateOptions($search))
                        ->getOptionLabelUsing(function ($value): ?string {
                            $query = Affiliate::query()->whereCommercialPurpose();

                            if ((bool) config('affiliates.owner.enabled', false)) {
                                $query->forOwner();
                            }

                            return $query->find($value)?->name;
                        })
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(static function (Set $set): void {
                            $set('conversion_ids', []);
                        })
                        ->required(),

                    Forms\Components\Select::make('conversion_ids')
                        ->label('Approved conversions')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(static fn (Get $get, ?string $search): array => CreateAffiliatePayout::conversionOptions(
                            $get('affiliate_id'),
                            $search,
                        ))
                        ->getOptionLabelsUsing(static fn (Get $get, array $values): array => CreateAffiliatePayout::conversionOptions(
                            $get('affiliate_id'),
                            null,
                            $values,
                        ))
                        ->disabled(static fn (Get $get): bool => blank($get('affiliate_id')))
                        ->required()
                        ->minItems(1)
                        ->helperText('Choose approved, unpaid conversions. The payout amount and currency are derived from these records.'),

                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Scheduled At'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3),

                    Forms\Components\Textarea::make('payout_override_reason')
                        ->label('Payout override reason')
                        ->helperText('Only disabled affiliates can use an audited override. Eligibility and override rules are enforced by the payout action.')
                        ->rows(2),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return AffiliatePayoutResource\Tables\AffiliatePayoutsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliatePayoutResource\Schemas\AffiliatePayoutInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            ConversionsRelationManager::class,
            PayoutEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliatePayouts::route('/'),
            'create' => CreateAffiliatePayout::route('/create'),
            'view' => ViewAffiliatePayout::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_payouts', 62);
    }
}
