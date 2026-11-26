<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations;

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

use function Filament\authorize;

final class PayoutsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'payouts';

    /** @return MorphMany<AffiliatePayout, Model> */
    #[Unexposed]
    public function getRelationship(): MorphMany
    {
        return $this->getOwnerRecord()->morphMany(AffiliatePayout::class, 'payee');
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Affiliate) {
            return false;
        }

        if (static::shouldSkipAuthorization()) {
            return true;
        }

        try {
            return authorize('viewAny', AffiliatePayout::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('total_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (AffiliatePayout $record): string => MoneyFormatter::formatMinor($record->total_minor, $record->currency))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('conversion_count')
                    ->label('Conversions')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliatePayout $record): string => AffiliatePayoutResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No payouts yet');
    }
}
