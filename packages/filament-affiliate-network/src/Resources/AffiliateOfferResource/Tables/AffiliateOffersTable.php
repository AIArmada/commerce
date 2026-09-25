<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables;

use AIArmada\AffiliateNetwork\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Carbon\CarbonImmutable;
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
                        self::transitionOffer($record, OfferStatus::Published);
                    }),
                Actions\Action::make('pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOffer $record): bool => $record->status === OfferStatus::Published)
                    ->action(function (AffiliateOffer $record): void {
                        self::transitionOffer($record, OfferStatus::Archived);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Transition an offer through the domain update action inside the owning
     * site's owner context.
     *
     * The admin table lists offers cross-tenant (scope bypassed), but
     * UpdateOffer re-queries under ScopesByBelongsToOwner. Entering the
     * site's own context makes that re-query match truthfully when
     * affiliate-network.owner.enabled=true, keeps the update on the domain
     * path (field allowlist, relocation guards, OfferUpdated event), and
     * maintains the published/archived lifecycle timestamps.
     */
    private static function transitionOffer(AffiliateOffer $record, OfferStatus $status): void
    {
        $site = $record->getRelationValue('site');

        if (! $site instanceof AffiliateSite) {
            $site = OwnerContext::withOwner(null, fn (): AffiliateSite => AffiliateSite::query()
                ->withoutOwnerScope()
                ->whereKey($record->site_id)
                ->firstOrFail());
        }

        /** @var string|null $ownerType */
        $ownerType = $site->owner_type;
        /** @var string|null $ownerId */
        $ownerId = $site->owner_id;

        OwnerContext::withOwner(OwnerContext::fromTypeAndId($ownerType, $ownerId), function () use ($record, $status): void {
            app(UpdateOffer::class)->execute($record, $status === OfferStatus::Published
                ? [
                    'status' => OfferStatus::Published,
                    'published_at' => CarbonImmutable::now(),
                    'archived_at' => null,
                ]
                : [
                    'status' => OfferStatus::Archived,
                    'archived_at' => CarbonImmutable::now(),
                ]);
        });
    }
}
