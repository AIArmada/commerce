<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\FilamentChip\Widgets\BankAccountStatusWidget;
use AIArmada\FilamentChip\Widgets\PayoutAmountWidget;
use AIArmada\FilamentChip\Widgets\PayoutStatsWidget;
use AIArmada\FilamentChip\Widgets\RecentPayoutsWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Throwable;

class PayoutDashboardPage extends Page
{
    public string $period = '30';

    /** @var array{total_payouts: int, completed_amount: float, pending_count: int, failed_count: int, active_accounts: int} */
    public array $metrics = [];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payout Dashboard';

    protected static ?string $title = 'CHIP Send Dashboard';

    protected static ?string $slug = 'chip/payouts';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-chip.navigation.group', 'Payments');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->loadMetrics();
    }

    public function loadMetrics(): void
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays((int) $this->period);

        $this->metrics = [
            'total_payouts' => SendInstruction::query()
                ->forOwner()
                ->where('created_at', '>=', $startDate)
                ->count(),
            'completed_amount' => (float) SendInstruction::query()
                ->forOwner()
                ->whereIn('state', ['completed', 'processed'])
                ->where('created_at', '>=', $startDate)
                ->sum('amount'),
            'pending_count' => SendInstruction::query()
                ->forOwner()
                ->whereIn('state', ['queued', 'received', 'verifying'])
                ->where('created_at', '>=', $startDate)
                ->count(),
            'failed_count' => SendInstruction::query()
                ->forOwner()
                ->whereIn('state', ['failed', 'cancelled', 'rejected'])
                ->where('created_at', '>=', $startDate)
                ->count(),
            'active_accounts' => BankAccount::query()
                ->forOwner()
                ->whereIn('status', ['active', 'approved'])
                ->count(),
        ];
    }

    public function updatedPeriod(): void
    {
        $this->loadMetrics();
    }

    public function render(): View
    {
        return view('filament-chip::pages.payout-dashboard', [
            'metrics' => $this->metrics,
            'period' => $this->period,
        ]);
    }

    /**
     * @return array<string, class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PayoutStatsWidget::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            PayoutAmountWidget::class,
            BankAccountStatusWidget::class,
            RecentPayoutsWidget::class,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('period_7')
                ->label('7 Days')
                ->outlined()
                ->action(function (): void {
                    $this->period = '7';
                    $this->loadMetrics();
                }),

            Action::make('period_30')
                ->label('30 Days')
                ->outlined()
                ->action(function (): void {
                    $this->period = '30';
                    $this->loadMetrics();
                }),

            Action::make('period_90')
                ->label('90 Days')
                ->outlined()
                ->action(function (): void {
                    $this->period = '90';
                    $this->loadMetrics();
                }),

            Action::make('sync_payouts')
                ->label('Sync from CHIP')
                ->icon(Heroicon::ArrowPath)
                ->color('info')
                ->action(function (): void {
                    try {
                        $service = app(ChipSendService::class);
                        $service->listSendInstructions();
                        Notification::make()
                            ->title('Payouts synced successfully')
                            ->success()
                            ->send();
                        $this->loadMetrics();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Failed to sync payouts')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('create_payout')
                ->label('Create Payout')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->url(fn (): string => route('filament.admin.resources.send-instructions.create')),
        ];
    }
}
