<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource\Tables;

use AIArmada\Affiliates\Exceptions\PayoutCompletionBlockedException;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\PayoutStatus;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Features\Payouts\Queries\PayoutExportService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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
                    ->color(fn (PayoutStatus | string $state): string => PayoutStatus::fromString($state)->color())
                    ->formatStateUsing(fn (PayoutStatus | string $state): string => PayoutStatus::fromString($state)->label())
                    ->sortable(),
                TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn (AffiliatePayout $record): string => MoneyFormatter::formatMinor($record->total_minor, $record->currency))
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
                    ->visible(fn (AffiliatePayout $record): bool => $record->status->canTransitionTo(CompletedPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        self::markCompleted($record);
                    }),
                Action::make('queue')
                    ->label('Mark Processing')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status->canTransitionTo(ProcessingPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = (bool) config('affiliates.owner.enabled', false)
                            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
                            : AffiliatePayout::findOrFail($record->getKey());

                        app(PayoutAdministration::class)->updateStatus((string) $payout->getKey(), ProcessingPayout::value());
                    }),
                Action::make('fail')
                    ->label('Mark Failed')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status->canTransitionTo(FailedPayout::class))
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = (bool) config('affiliates.owner.enabled', false)
                            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
                            : AffiliatePayout::findOrFail($record->getKey());

                        app(PayoutAdministration::class)->updateStatus((string) $payout->getKey(), FailedPayout::value());
                    }),
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.export') ?? false)
                    ->action(function (AffiliatePayout $record) {
                        Gate::authorize('export', $record);

                        $payout = (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
            : AffiliatePayout::findOrFail($record->getKey());

                        return app(PayoutExportService::class)->downloadCsv($payout);
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * Complete a payout from the Mark Completed row action.
     *
     * Completion-gate refusals surface as a danger notification
     * carrying the gate message instead of a raw exception.
     */
    public static function markCompleted(AffiliatePayout $record): void
    {
        Gate::authorize('update', $record);

        $payout = (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
            : AffiliatePayout::findOrFail($record->getKey());

        try {
            app(PayoutAdministration::class)->updateStatus((string) $payout->getKey(), CompletedPayout::value());
        } catch (PayoutCompletionBlockedException $exception) {
            Notification::make()
                ->danger()
                ->title('Payout cannot be completed')
                ->body($exception->getMessage())
                ->send();
        }
    }
}
