<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Tables;

use AIArmada\AffiliateNetwork\Actions\ReinstateSite;
use AIArmada\AffiliateNetwork\Actions\RejectSite;
use AIArmada\AffiliateNetwork\Actions\SuspendSite;
use AIArmada\AffiliateNetwork\Actions\SyncSiteOffers;
use AIArmada\AffiliateNetwork\Actions\VerifySite;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AffiliateSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AffiliateSite::STATUS_VERIFIED => 'success',
                        AffiliateSite::STATUS_PENDING => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('offers_count')
                    ->label('Offers')
                    ->counts('offers')
                    ->sortable(),

                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        AffiliateSite::STATUS_PENDING => 'Pending',
                        AffiliateSite::STATUS_VERIFIED => 'Verified',
                        AffiliateSite::STATUS_SUSPENDED => 'Suspended',
                        AffiliateSite::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('sync_catalog')
                    ->label('Sync catalog')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (AffiliateSite $record): array {
                        $result = app(SyncSiteOffers::class)->execute($record);

                        Notification::make()
                            ->title('Catalog sync complete')
                            ->body(sprintf(
                                '%d program(s): %d created, %d updated, %d skipped, %d failed.',
                                $result['programs'],
                                $result['created'],
                                $result['updated'],
                                $result['skipped'],
                                $result['failed'],
                            ))
                            ->success()
                            ->send();

                        return $result;
                    }),
                Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(VerifySite::class)->execute($record));
                    }),
                Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(RejectSite::class)->execute($record));
                    }),
                Actions\Action::make('suspend')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isVerified())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(SuspendSite::class)->execute($record));
                    }),
                Actions\Action::make('reinstate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isSuspended())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(ReinstateSite::class)->execute($record));
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private static function withSiteOwner(AffiliateSite $record, callable $callback): mixed
    {
        $owner = OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);

        return OwnerContext::withOwner($owner, $callback);
    }
}
