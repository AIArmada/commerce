<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Filament\Actions;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AffiliateOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AffiliateOffer::STATUS_ACTIVE => 'success',
                        AffiliateOffer::STATUS_PENDING => 'warning',
                        AffiliateOffer::STATUS_PAUSED => 'info',
                        AffiliateOffer::STATUS_DRAFT => 'gray',
                        default => 'danger',
                    }),

                TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->formatStateUsing(function (AffiliateOffer $record): string {
                        if ($record->commission_type === 'percentage') {
                            return number_format($record->commission_rate / 100, 2) . '%';
                        }

                        return MoneyFormatter::formatMinor($record->commission_rate, $record->currency ?? 'USD');
                    })
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('applications_count')
                    ->label('Applications')
                    ->counts('applications')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        AffiliateOffer::STATUS_DRAFT => 'Draft',
                        AffiliateOffer::STATUS_PENDING => 'Pending',
                        AffiliateOffer::STATUS_ACTIVE => 'Active',
                        AffiliateOffer::STATUS_PAUSED => 'Paused',
                        AffiliateOffer::STATUS_EXPIRED => 'Expired',
                        AffiliateOffer::STATUS_REJECTED => 'Rejected',
                    ]),

                // Admin filter: cross-tenant — show all sites for filtering.
                SelectFilter::make('site_id')
                    ->label('Site')
                    ->relationship('site', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope(OwnerScope::class)),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),

                TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status !== AffiliateOffer::STATUS_ACTIVE)
                    ->action(function (AffiliateOffer $record): void {
                        // Admin resource bypasses owner_via_site scope (network-wide admin view).
                        $scopedRecord = OwnerContext::withOwner(null, fn (): AffiliateOffer => AffiliateOffer::withoutGlobalScope('owner_via_site')
                            ->whereKey($record->getKey())
                            ->firstOrFail());

                        $scopedRecord->update(['status' => AffiliateOffer::STATUS_ACTIVE]);
                    }),
                Actions\Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status === AffiliateOffer::STATUS_ACTIVE)
                    ->action(function (AffiliateOffer $record): void {
                        // Admin resource bypasses owner_via_site scope (network-wide admin view).
                        $scopedRecord = OwnerContext::withOwner(null, fn (): AffiliateOffer => AffiliateOffer::withoutGlobalScope('owner_via_site')
                            ->whereKey($record->getKey())
                            ->firstOrFail());

                        $scopedRecord->update(['status' => AffiliateOffer::STATUS_PAUSED]);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
