<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\FilamentAffiliates\Actions\ProcessAffiliatePayout;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
use Illuminate\Support\Number;
use UnitEnum;

final class PayoutBatchPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Payout Batch';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.pages.navigation_sort.payout_batch', 12);
    }

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.payout-batch';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                    ->where('status', PendingPayout::value())
                    ->with(['affiliate'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record): string => Number::currency($state / 100, $record->currency ?? 'USD'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('payout_method')
                    ->label('Method')
                    ->badge()
                    ->getStateUsing(function (AffiliatePayout $record): string {
                        $method = $record->affiliate
                            ?->payoutMethods()
                            ->where('is_default', true)
                            ->first();

                        return $method?->type?->value ?? '—';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn () => OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (AffiliatePayout $record, array $data): void {
                        Gate::authorize('update', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $fromStatus = $payout->status;

                        $payout->update([
                            'status' => FailedPayout::class,
                            'metadata' => array_merge($payout->metadata ?? [], [
                                'notes' => $data['reason'],
                            ]),
                        ]);

                        $payout->events()->create([
                            'from_status' => $fromStatus?->getValue(),
                            'to_status' => FailedPayout::value(),
                            'notes' => $data['reason'],
                        ]);

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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->action(function (Collection $records): void {
                        $success = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            Gate::authorize('update', $record);

                            $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                                ->whereKey($record->getKey())
                                ->firstOrFail();

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
        $pending = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
            ->where('status', PendingPayout::value());

        return [
            'pendingCount' => $pending->count(),
            'pendingTotal' => $pending->sum('total_minor'),
            'pendingByCurrency' => OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                ->where('status', PendingPayout::value())
                ->selectRaw('currency, SUM(total_minor) as total, COUNT(*) as count')
                ->groupBy('currency')
                ->get(),
        ];
    }

}
