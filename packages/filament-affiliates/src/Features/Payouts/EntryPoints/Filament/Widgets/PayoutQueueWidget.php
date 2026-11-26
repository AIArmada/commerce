<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Widgets;

use AIArmada\Affiliates\Features\Payouts\Api\PayoutAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Gate;
use LogicException;

final class PayoutQueueWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected static ?string $heading = 'Pending Payouts';

    public function table(Table $table): Table
    {
        $payout = new AffiliatePayout;
        $conversion = new AffiliateConversion;

        if ($payout->getConnection() !== $conversion->getConnection()) {
            throw new LogicException('Payout queue requires a shared database connection.');
        }

        $count = AffiliateConversion::query()->forOwner(includeGlobal: true)
            ->whereColumn($conversion->qualifyColumn('affiliate_payout_id'), $payout->qualifyColumn('id'))
            ->selectRaw('COUNT(*)');

        if (config('affiliates.owner.enabled', false)) {
            foreach (['owner_type', 'owner_id'] as $column) {
                $left = $conversion->qualifyColumn($column);
                $right = $payout->qualifyColumn($column);
                $count->where(static fn ($query) => $query->whereColumn($left, $right)
                    ->orWhere(static fn ($query) => $query->whereNull($left)->whereNull($right)));
            }
        }

        return $table
            ->query(
                AffiliatePayout::query()
                    ->with('payee')
                    ->addSelect(['conversions_count' => $count])
                    ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
                    ->whereHas('payee', static function ($query): void {
                        $query->whereCommercialPurpose();
                    })
                    ->orderBy('scheduled_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee.name')
                    ->label('Affiliate')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (AffiliatePayout $record): string => MoneyFormatter::formatMinor((int) $record->total_minor, $record->currency))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => PendingPayout::value(),
                        'info' => ProcessingPayout::value(),
                    ]),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->numeric(),
            ])
            ->actions([
                Action::make('process')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status->equals(PendingPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = (bool) config('affiliates.owner.enabled', false)
                    ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
                    : AffiliatePayout::findOrFail($record->getKey());

                        $result = app(PayoutAdministration::class)->process((string) $payout->getKey());

                        if ($result->success) {
                            Notification::make()
                                ->success()
                                ->title('Payout processed')
                                ->body('External reference: ' . ($result->externalReference ?? '—'))
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->danger()
                            ->title('Payout failed')
                            ->body($result->failureReason ?? 'Unknown error')
                            ->send();
                    }),

                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AffiliatePayout $record): string => AffiliatePayoutResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No pending payouts')
            ->emptyStateDescription('All payouts have been processed.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    protected function getTableHeading(): ?string
    {
        $pendingCount = AffiliatePayout::query()
            ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
            ->whereHas('payee', static function ($query): void {
                $query->whereCommercialPurpose();
            })
            ->count();

        return "Pending Payouts ({$pendingCount})";
    }
}
