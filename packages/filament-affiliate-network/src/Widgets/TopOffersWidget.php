<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Widgets;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class TopOffersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Admin leaderboard: intentionally cross-tenant network-wide — explicit global context.
                OwnerContext::withOwner(null, fn () => AffiliateOffer::withoutGlobalScope('owner_via_site')
                    ->with([
                        'site' => fn ($query) => $query->withoutOwnerScope(),
                    ])
                    ->where('status', OfferStatus::Published)
                    ->withSum('links', 'clicks')
                    ->withSum('links', 'conversions')
                    ->withSum('links', 'revenue')
                    ->orderByDesc('links_sum_clicks')
                    ->limit(10))
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

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->formatStateUsing(function (AffiliateOffer $record): string {
                        if ($record->commission_type === 'percentage') {
                            return number_format($record->commission_rate / 100, 2) . '%';
                        }

                        return MoneyFormatter::formatMinor($record->commission_rate, $record->currency ?? 'USD');
                    }),
            ])
            ->heading('Top Performing Offers')
            ->paginated(false);
    }
}
