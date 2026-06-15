<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PortalConversions extends PortalPage implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 2;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.conversions';

    public static function getNavigationLabel(): string
    {
        return __('Conversions');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Conversion History');
    }

    public function table(Table $table): Table
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return $table
                ->query(AffiliateConversion::query()->whereNull('id'))
                ->columns([])
                ->emptyStateHeading(__('No affiliate account'))
                ->emptyStateDescription(__('You need an affiliate account to view conversions.'));
        }

        $affiliateId = $affiliate->getKey();

        $query = AffiliateConversion::query()
            ->where('affiliate_id', $affiliateId);

        if (config('affiliates.network.enabled', false)) {
            $descendantIds = AffiliateNetwork::query()
                ->where('ancestor_id', $affiliateId)
                ->where('depth', '>', 0)
                ->pluck('descendant_id')
                ->toArray();

            if ($descendantIds !== []) {
                $query->orWhereIn('affiliate_id', $descendantIds);
            }
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('affiliate.code')
                    ->label(__('Affiliate'))
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => config('affiliates.network.enabled', false)),

                TextColumn::make('voucher_code')
                    ->label(__('Source'))
                    ->badge()
                    ->getStateUsing(function (AffiliateConversion $record) use ($affiliateId): string {
                        if ($record->affiliate_id !== $affiliateId) {
                            return __('Network');
                        }

                        return match (true) {
                            $record->channel === 'upline' => __('Network'),
                            $record->voucher_code !== null => __('Voucher'),
                            default => __('Link'),
                        };
                    })
                    ->color(function (AffiliateConversion $record) use ($affiliateId): string {
                        if ($record->affiliate_id !== $affiliateId) {
                            return 'success';
                        }

                        return match (true) {
                            $record->channel === 'upline' => 'warning',
                            $record->voucher_code !== null => 'info',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('external_reference')
                    ->label(__('Reference'))
                    ->searchable(),

                TextColumn::make('value_minor')
                    ->label(__('Total'))
                    ->formatStateUsing(fn ($state) => $this->formatAmount((int) $state))
                    ->sortable(),

                TextColumn::make('commission_minor')
                    ->label(__('Commission'))
                    ->formatStateUsing(fn ($state) => $this->formatAmount((int) $state))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string | BackedEnum $state): string => ConversionStatus::colorFor(
                        $state instanceof BackedEnum ? $state->value : $state
                    )),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        $ownConversions = $affiliate
            ? (int) $affiliate->conversions()->count()
            : 0;

        $networkConversions = 0;

        if ($affiliate && config('affiliates.network.enabled', false)) {
            $descendantIds = AffiliateNetwork::query()
                ->where('ancestor_id', $affiliate->getKey())
                ->where('depth', '>', 0)
                ->pluck('descendant_id')
                ->toArray();

            if ($descendantIds !== []) {
                $networkConversions = (int) AffiliateConversion::query()
                    ->whereIn('affiliate_id', $descendantIds)
                    ->count();
            }
        }

        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'totalConversions' => $ownConversions + $networkConversions,
            'totalEarnings' => $this->getTotalEarnings(),
            'pendingEarnings' => $this->getPendingEarnings(),
        ];
    }
}
