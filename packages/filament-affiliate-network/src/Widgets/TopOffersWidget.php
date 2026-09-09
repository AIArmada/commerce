<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Widgets;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class TopOffersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return NetworkAdminAccess::allows();
    }

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        /** @var array<int, string> $offerIds */
        $offerIds = OwnerCache::remember(
            null,
            'affiliate-network.top-offer-ids',
            now()->addSeconds(30),
            fn (): array => OwnerContext::withOwner(null, fn (): array => AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
                ->where('status', OfferStatus::Published)
                ->withSum(['links' => fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class)], 'clicks')
                ->orderByDesc('links_sum_clicks')
                ->limit(10)
                ->pluck('id')
                ->all()),
        );

        return $table
            ->query(
                // Admin leaderboard: intentionally cross-tenant network-wide — explicit global context.
                OwnerContext::withOwner(
                    null,
                    fn (): Builder => AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
                        ->whereKey($offerIds)
                        ->where('status', OfferStatus::Published)
                        ->with([
                            'site' => fn ($query) => $query->withoutOwnerScope(),
                        ])
                        ->withSum(['links' => fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class)], 'clicks')
                        ->withSum(['links' => fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class)], 'conversions')
                        ->withSum(['links' => fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class)], 'revenue')
                        ->orderByDesc('links_sum_clicks')
                )
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Offer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site'),

                Tables\Columns\TextColumn::make('links_sum_clicks')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('links_sum_conversions')
                    ->label('Conversions')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('links_sum_revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state, AffiliateOffer $record): string => MoneyFormatter::formatMinor((int) ($state ?? 0), $record->currency ?? 'USD'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('rate_base_bp')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateOffer $record): string => $record->formattedRate()),
            ])
            ->heading('Top Performing Offers')
            ->paginated(false);
    }
}
