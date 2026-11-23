<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables;

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\ArchiveOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\PublishOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Sites\Api\SitesApi;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
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
                    ->color(fn (OfferStatus | string $state): string => $state instanceof OfferStatus ? $state->color() : (OfferStatus::tryFrom((string) $state)?->color() ?? 'gray')),

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
                    ->formatStateUsing(fn (OfferSource | string $state): string => $state instanceof OfferSource ? $state->label() : (OfferSource::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (OfferSource | string $state): string => $state instanceof OfferSource ? $state->color() : (OfferSource::tryFrom((string) $state)?->color() ?? 'gray'))
                    ->toggleable(),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (OfferVisibility | string $state): string => $state instanceof OfferVisibility ? $state->color() : (OfferVisibility::tryFrom((string) $state)?->color() ?? 'gray'))
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
                    ->options(self::statusOptions()),

                SelectFilter::make('visibility')
                    ->options(self::visibilityOptions()),

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
                    ->disabled(fn (AffiliateOffer $record): bool => ! self::isSiteVerified((string) $record->site_id))
                    ->tooltip(fn (AffiliateOffer $record): ?string => self::isSiteVerified((string) $record->site_id)
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
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (OfferStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function visibilityOptions(): array
    {
        $options = [];

        foreach (OfferVisibility::cases() as $visibility) {
            $options[$visibility->value] = $visibility->label();
        }

        return $options;
    }

    private static function isSiteVerified(string $siteId): bool
    {
        return app(SitesApi::class)->isVerified($siteId);
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
