<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource\RelationManagers;

use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function Filament\authorize;

final class ConversionsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'conversions';

    /** @return HasMany<AffiliateConversion, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof AffiliatePayout) {
            return false;
        }

        if (static::shouldSkipAuthorization()) {
            return true;
        }

        try {
            return authorize('viewAny', AffiliateConversion::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate_code')
                    ->label('Affiliate')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('external_reference')
                    ->label('Reference')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->placeholder('—'),
                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateConversion $record): string => MoneyFormatter::formatMinor($record->commission_minor, $record->commission_currency))
                    ->badge()
                    ->color('success'),
                TextColumn::make('status')
                    ->badge()
                    ->color('info'),
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime(),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No conversions assigned');
    }
}
