<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

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

        return Affiliate::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', AffiliateStatus::Active)
            ->withCount('conversions')
            ->withSum('conversions', 'commission_minor')
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
                    ->label('Commissions')
                    ->formatStateUsing(fn ($state): string => 'RM '.Number::format(($state ?? 0) / 100, 2))
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
