<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Tables;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use AIArmada\Affiliates\States\CompletedPayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PayoutStatus;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

final class AffiliatePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->copyable()
                    ->icon(Heroicon::OutlinedIdentification)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PayoutStatus | string $state): string => PayoutStatus::colorFor($state))
                    ->formatStateUsing(fn (PayoutStatus | string $state): string => PayoutStatus::labelFor($state))
                    ->sortable(),
                TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn (AffiliatePayout $record): string => sprintf(
                        '%s %.2f',
                        $record->currency,
                        $record->total_minor / 100
                    ))
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('conversion_count')
                    ->label('Conversions')
                    ->badge()
                    ->color('info'),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PayoutStatus::options()),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliatePayout $record): string => AffiliatePayoutResource::getUrl('view', ['record' => $record])),
                Action::make('mark_paid')
                    ->label('Mark Completed')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn (AffiliatePayout $record): bool => ! $record->status->equals(CompletedPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        app(AffiliatePayoutService::class)->updateStatus($payout, CompletedPayout::value());
                    }),
                Action::make('queue')
                    ->label('Mark Processing')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status->equals(PendingPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        app(AffiliatePayoutService::class)->updateStatus($payout, ProcessingPayout::value());
                    }),
                Action::make('fail')
                    ->label('Mark Failed')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn (AffiliatePayout $record): bool => ! $record->status->equals(FailedPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        app(AffiliatePayoutService::class)->updateStatus($payout, FailedPayout::value());
                    }),
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.export') ?? false)
                    ->action(function (AffiliatePayout $record) {
                        Gate::authorize('export', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        return app(PayoutExportService::class)->download($payout);
                    }),
            ])
            ->bulkActions([]);
    }
}
