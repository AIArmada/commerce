<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables;

use AIArmada\AffiliateNetwork\Actions\ArchiveOffer;
use AIArmada\AffiliateNetwork\Actions\PublishOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
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

                TextColumn::make('rate_base_bp')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateOffer $record): string => $record->formattedRate())
                    ->sortable(),

                TextColumn::make('network_fee_bp')
                    ->label('Fee (bp)')
                    ->placeholder('default')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'manual' ? 'warning' : 'success')
                    ->toggleable(),

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
                    ->disabled(fn (AffiliateOffer $record): bool => ! AffiliateSite::isVerifiedKey($record->site_id))
                    ->tooltip(fn (AffiliateOffer $record): ?string => AffiliateSite::isVerifiedKey($record->site_id)
                        ? null
                        : 'Verify the site before publishing its offers.')
                    ->action(function (AffiliateOffer $record): void {
                        self::withOfferOwner($record, fn (): AffiliateOffer => app(PublishOffer::class)->execute($record));
                    }),
                Actions\Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status === OfferStatus::Published)
                    ->action(function (AffiliateOffer $record): void {
                        self::withOfferOwner($record, fn (): AffiliateOffer => app(ArchiveOffer::class)->execute($record));
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
    private static function withOfferOwner(AffiliateOffer $record, callable $callback): mixed
    {
        $site = OwnerContext::withOwner(null, fn (): AffiliateSite => AffiliateSite::query()
            ->withoutOwnerScope()
            ->whereKey($record->site_id)
            ->firstOrFail());

        $owner = OwnerContext::fromTypeAndId($site->owner_type, $site->owner_id);

        return OwnerContext::withOwner($owner, $callback);
    }
}
