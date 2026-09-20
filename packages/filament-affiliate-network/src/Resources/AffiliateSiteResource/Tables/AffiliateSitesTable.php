<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Tables;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Actions\SyncSiteCatalog;
use Carbon\CarbonImmutable;
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
                    ->action(fn (AffiliateSite $record): array => app(SyncSiteCatalog::class)->handle($record)),
                Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->action(function (AffiliateSite $record): void {
                        // Admin table action: run inside the site's own owner context so the
                        // re-fetch matches under owner scoping and the HasOwner saving guard
                        // accepts the write (explicit-global would 404/deny owned sites).
                        $owner = OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);

                        OwnerContext::withOwner($owner, function () use ($record): void {
                            $scopedRecord = AffiliateSite::query()
                                ->whereKey($record->getKey())
                                ->firstOrFail();

                            $scopedRecord->update([
                                'status' => AffiliateSite::STATUS_VERIFIED,
                                'verified_at' => CarbonImmutable::now(),
                            ]);
                        });
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
