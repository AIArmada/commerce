<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
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
                    ->color(fn (OfferStatus $state): string => $state->color()),

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

                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (OfferVisibility $state): string => $state->color())
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
                        OfferStatus::Draft->value => 'Draft',
                        OfferStatus::Published->value => 'Published',
                        OfferStatus::Archived->value => 'Archived',
                    ]),

                SelectFilter::make('visibility')
                    ->options([
                        OfferVisibility::Public->value => 'Public',
                        OfferVisibility::Private->value => 'Private',
                        OfferVisibility::Unlisted->value => 'Unlisted',
                    ]),

                // Admin filter: cross-tenant — show all sites for filtering.
                SelectFilter::make('site_id')
                    ->label('Site')
                    ->relationship('site', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->withoutGlobalScope(OwnerScope::class)),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status !== OfferStatus::Published)
                    ->action(function (AffiliateOffer $record): void {
                        // Admin resource bypasses owner_via_site scope (network-wide admin view).
                        $scopedRecord = OwnerContext::withOwner(null, fn (): AffiliateOffer => AffiliateOffer::withoutGlobalScope('owner_via_site')
                            ->whereKey($record->getKey())
                            ->firstOrFail());

                        $scopedRecord->update(['status' => OfferStatus::Published]);
                    }),
                Actions\Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status === OfferStatus::Published)
                    ->action(function (AffiliateOffer $record): void {
                        // Admin resource bypasses owner_via_site scope (network-wide admin view).
                        $scopedRecord = OwnerContext::withOwner(null, fn (): AffiliateOffer => AffiliateOffer::withoutGlobalScope('owner_via_site')
                            ->whereKey($record->getKey())
                            ->firstOrFail());

                        $scopedRecord->update(['status' => OfferStatus::Archived]);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
