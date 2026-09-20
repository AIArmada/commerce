<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Actions\Payouts\UpdatePayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Actions\ProcessAffiliatePayout;
use AIArmada\FilamentAffiliates\Actions\RunScheduledPayoutSweep;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

final class PayoutBatchPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Payout Batch';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.pages.navigation_sort.payout_batch', 12);
    }

    public static function canAccess(): bool
    {
        return FilamentPermission::hasAnyAbility(['affiliate.payout', 'affiliates.payout.update']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.payout-batch';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_settlement')
                ->label('Export settlement CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Forms\Components\DatePicker::make('from')
                        ->label('From')
                        ->nullable(),
                    Forms\Components\DatePicker::make('to')
                        ->label('To')
                        ->nullable(),
                ])
                ->action(fn (array $data) => app(PayoutExportService::class)->downloadSettlementCsv(
                    $data['from'] ?? null,
                    $data['to'] ?? null,
                )),

            Action::make('scheduled_sweep')
                ->label('Scheduled sweep')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.payout', 'affiliates.payout.update']))
                ->form([
                    Forms\Components\Toggle::make('dry_run')
                        ->label('Dry run (preview only)')
                        ->default(true),

                    Forms\Components\TextInput::make('affiliate_id')
                        ->label('Affiliate ID (optional)')
                        ->nullable(),

                    Forms\Components\TextInput::make('min_amount')
                        ->label('Minimum floor (minor units, optional)')
                        ->numeric()
                        ->minValue(0)
                        ->nullable()
                        ->helperText('Applied in each balance currency. Blank means any positive balance.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Run scheduled payout sweep')
                ->modalDescription('Atomically claims scheduled payouts for eligible balances in the current owner scope. Dry runs change nothing.')
                ->action(function (array $data): void {
                    $dryRun = (bool) ($data['dry_run'] ?? true);
                    $minimum = isset($data['min_amount']) && $data['min_amount'] !== ''
                        ? max(0, (int) $data['min_amount'])
                        : 0;

                    $summary = app(RunScheduledPayoutSweep::class)->handle(
                        $dryRun,
                        is_string($data['affiliate_id'] ?? null) ? $data['affiliate_id'] : null,
                        $minimum,
                    );

                    Notification::make()
                        ->title($dryRun ? 'Sweep dry run complete' : 'Scheduled sweep complete')
                        ->body("Processed: {$summary['processed']}, Skipped: {$summary['skipped']}, Errors: {$summary['errors']}")
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliatePayout::query()
                    ->where('status', PendingPayout::value())
                    ->with(['payee', 'payee.payoutMethods' => fn ($query) => $query->where('is_default', true)])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('payee.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payee.name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record): string => MoneyFormatter::formatMinor((int) $state, $record->currency ?? 'MYR'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('payout_method')
                    ->label('Method')
                    ->badge()
                    ->getStateUsing(function (AffiliatePayout $record): string {
                        $payee = $record->payee;

                        if (! $payee instanceof Affiliate) {
                            return '—';
                        }

                        $method = $payee->relationLoaded('payoutMethods')
                            ? $payee->payoutMethods->firstWhere('is_default', true)
                            : $payee->payoutMethods()->where('is_default', true)->first();

                        return $method?->type?->value ?? '—';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn () => AffiliatePayout::query()
                        ->distinct()
                        ->pluck('currency', 'currency')
                        ->toArray()),
            ])
            ->actions([
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.payout', 'affiliates.payout.update']))
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
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Payout failed')
                                ->body($result->failureReason ?? 'Unknown error')
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.payout', 'affiliates.payout.update']))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (AffiliatePayout $record, array $data): void {
                        Gate::authorize('update', $record);

                        $payout = (bool) config('affiliates.owner.enabled', false)
                            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
                            : AffiliatePayout::findOrFail($record->getKey());

                        $payout = app(UpdatePayoutStatus::class)->handle(
                            $payout,
                            FailedPayout::value(),
                            $data['reason'],
                        );

                        // Fund release + conversion unlink are handled inside UpdatePayoutStatus.
                        $payout->forceFill([
                            'metadata' => array_merge($payout->metadata ?? [], [
                                'notes' => $data['reason'],
                            ]),
                        ])->save();

                        Notification::make()
                            ->warning()
                            ->title('Payout rejected')
                            ->send();
                    }),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('batch_process')
                    ->label('Process All Selected')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.payout', 'affiliates.payout.update']))
                    ->action(function (Collection $records): void {
                        $success = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            Gate::authorize('update', $record);

                            $payout = (bool) config('affiliates.owner.enabled', false)
                            ? OwnerWriteGuard::findOrFailForOwner(AffiliatePayout::class, $record->getKey())
                            : AffiliatePayout::findOrFail($record->getKey());

                            $result = app(ProcessAffiliatePayout::class)->handle($payout);

                            if ($result->success) {
                                $success++;
                            } else {
                                $failed++;
                            }
                        }

                        Notification::make()
                            ->title('Batch processing complete')
                            ->body("Processed: {$success}, Failed: {$failed}")
                            ->send();
                    }),
            ]);
    }

    public function getViewData(): array
    {
        $pendingByCurrency = AffiliatePayout::query()
            ->where('status', PendingPayout::value())
            ->selectRaw('currency, SUM(total_minor) as total, COUNT(*) as count')
            ->groupBy('currency')
            ->get();

        return [
            'pendingCount' => (int) $pendingByCurrency->sum(fn (AffiliatePayout $row): int => (int) $row->getAttribute('count')),
            'pendingByCurrency' => $pendingByCurrency,
        ];
    }
}
