<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class TopAffiliates extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected static ?string $heading = '🏆 Top Affiliates';

    /**
     * @return Builder<Affiliate>
     */
    protected function getTableQuery(): Builder
    {
        $owner = OwnerContext::resolve();
        $affiliate = new Affiliate;
        $conversion = new AffiliateConversion;

        if ($affiliate->getConnection() !== $conversion->getConnection()) {
            throw new LogicException('Affiliate ranking requires a shared database connection.');
        }

        $conversions = AffiliateConversion::query()->forOwner(includeGlobal: true)
            ->whereColumn($conversion->qualifyColumn('affiliate_id'), $affiliate->qualifyColumn('id'));
        $currency = mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));

        return Affiliate::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->addSelect([
                'conversions_count' => (clone $conversions)->selectRaw('COUNT(*)'),
                'conversions_sum_commission_minor' => (clone $conversions)->where('commission_currency', $currency)->selectRaw('COALESCE(SUM(commission_minor), 0)'),
            ])
            ->orderByDesc('conversions_sum_commission_minor')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Affiliate')
                    ->description(fn (Affiliate $record): ?string => $record->metadata['platform'] ?? null)
                    ->limit(25),

                TextColumn::make('conversions_count')
                    ->label('Sales')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                TextColumn::make('conversions_sum_commission_minor')
                    ->label('Commissions ('.mb_strtoupper((string) config('affiliates.currency.default', 'MYR')).' only)')
                    ->formatStateUsing(fn ($state): string => MoneyFormatter::formatMinor((int) $state, mb_strtoupper((string) config('affiliates.currency.default', 'MYR'))))
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
