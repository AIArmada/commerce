<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Tables;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\ReinstateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RejectSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\SuspendSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySite;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Actions\SyncSiteCatalog;
use Filament\Actions;
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
                    ->formatStateUsing(fn (SiteStatus | string $state): string => $state instanceof SiteStatus ? $state->label() : (SiteStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (SiteStatus | string $state): string => $state instanceof SiteStatus ? $state->color() : (SiteStatus::tryFrom((string) $state)?->color() ?? 'gray')),

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
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Actions\EditAction::make(),
                SyncSiteCatalog::make(),
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
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (SiteStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
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
