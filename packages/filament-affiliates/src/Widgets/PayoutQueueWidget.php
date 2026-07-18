<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Actions\ProcessAffiliatePayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Gate;

final class PayoutQueueWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected static ?string $heading = 'Pending Payouts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliatePayout::query()
                    ->with('payee')
                    ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
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
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => PendingPayout::value(),
                        'info' => ProcessingPayout::value(),
                    ]),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->counts('conversions'),
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

                        $result = app(ProcessAffiliatePayout::class)->handle($payout);

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
            ->count();

        return "Pending Payouts ({$pendingCount})";
    }
}
