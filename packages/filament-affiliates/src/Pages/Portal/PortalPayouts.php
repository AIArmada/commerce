<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class PortalPayouts extends PortalPage implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.payouts';

    public static function getNavigationLabel(): string
    {
        return __('Payouts');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Payout History');
    }

    public function table(Table $table): Table
    {
        $affiliate = $this->getAffiliate();

        return $table
            ->query(
                AffiliatePayout::query()
                    ->when($affiliate, fn (Builder $query) => $query
                        ->where('payee_type', $affiliate->getMorphClass())
                        ->where('payee_id', $affiliate->getKey()))
                    ->when(! $affiliate, fn (Builder $query) => $query->whereRaw('1 = 0'))
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reference')
                    ->label(__('Reference'))
                    ->searchable(),

                TextColumn::make('total_minor')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn ($state, AffiliatePayout $record): string => $this->formatAmount((int) $state, $record->currency))
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string | BackedEnum $state): string => PayoutStatus::fromString(
                        $state instanceof BackedEnum ? (string) $state->value : $state
                    )->color()),

                TextColumn::make('paid_at')
                    ->label(__('Paid At'))
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();
        /** @var array<string, int> $totalPaid */
        $totalPaid = [];

        if ($affiliate) {
            $rows = AffiliatePayout::query()
                ->where('payee_type', $affiliate->getMorphClass())
                ->where('payee_id', $affiliate->getKey())
                ->where('status', CompletedPayout::value())
                ->selectRaw('currency as ccy, SUM(total_minor) as total')
                ->groupBy('currency')
                ->pluck('total', 'ccy');

            foreach ($rows as $code => $total) {
                $key = mb_strtoupper((string) $code);
                $totalPaid[$key] = ($totalPaid[$key] ?? 0) + (int) $total;
            }

            ksort($totalPaid);
        }

        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'totalPaid' => $totalPaid,
            'availableEarnings' => $this->getAvailableEarnings(),
            'pendingEarnings' => $this->getPendingEarnings(),
        ];
    }
}
