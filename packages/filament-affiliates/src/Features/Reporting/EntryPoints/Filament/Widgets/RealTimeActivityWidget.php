<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets;

use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentAffiliates\Support\AffiliateReferenceProjection;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

final class RealTimeActivityWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

    protected static ?string $heading = 'Real-Time Activity';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateReferenceProjection::apply(AffiliateConversion::query()->forOwner())
                    ->latest('occurred_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate_name')
                    ->label('Affiliate')
                    ->searchable(query: fn (Builder $query, string $search): Builder => AffiliateReferenceProjection::search($query, $search)),

                Tables\Columns\TextColumn::make('external_reference')
                    ->label('Reference')
                    ->limit(15),

                Tables\Columns\TextColumn::make('value_minor')
                    ->label('Value')
                    ->formatStateUsing(fn (AffiliateConversion $record): string => MoneyFormatter::formatMinor((int) $record->value_minor, $record->commission_currency))
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateConversion $record): string => MoneyFormatter::formatMinor((int) $record->commission_minor, $record->commission_currency))
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'qualified',
                        'success' => ['approved', 'paid'],
                        'danger' => 'rejected',
                    ]),
            ])
            ->paginated(false)
            ->defaultSort('occurred_at', 'desc');
    }
}
