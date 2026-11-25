<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource\RelationManagers;

use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function Filament\authorize;

final class PayoutEventsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'events';

    /** @return HasMany<AffiliatePayoutEvent, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(AffiliatePayoutEvent::class, 'affiliate_payout_id')->latest();
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
            return authorize('viewAny', AffiliatePayoutEvent::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_status')
                    ->label('From')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('to_status')
                    ->label('To')
                    ->badge(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No payout events yet');
    }
}
