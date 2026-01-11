<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\FilamentChip\Widgets\AccountBalanceWidget;
use AIArmada\FilamentChip\Widgets\AccountTurnoverWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Throwable;

class FinancialOverviewPage extends Page
{
    /** @var array{available: int, pending: int, reserved: int, total: int} */
    public array $balance = [
        'available' => 0,
        'pending' => 0,
        'reserved' => 0,
        'total' => 0,
    ];

    /** @var array{income: int, fees: int, refunds: int, net: int} */
    public array $turnover = [
        'income' => 0,
        'fees' => 0,
        'refunds' => 0,
        'net' => 0,
    ];

    public bool $hasError = false;

    public string $errorMessage = '';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Financial Overview';

    protected static ?string $title = 'Financial Overview';

    protected static ?string $slug = 'chip/financials';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament-chip::pages.financial-overview';

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
        $this->loadFinancialData();
    }

    public function loadFinancialData(): void
    {
        $this->hasError = false;
        $this->errorMessage = '';

        try {
            $service = app(ChipCollectService::class);

            $balanceResponse = $service->getAccountBalance();
            $this->balance = [
                'available' => (int) ($balanceResponse['available_balance'] ?? $balanceResponse['available'] ?? 0),
                'pending' => (int) ($balanceResponse['pending_balance'] ?? $balanceResponse['pending'] ?? 0),
                'reserved' => (int) ($balanceResponse['reserved_balance'] ?? $balanceResponse['reserved'] ?? 0),
                'total' => 0,
            ];
            $this->balance['total'] = $this->balance['available'] + $this->balance['pending'];

            $turnoverResponse = $service->getAccountTurnover([
                'date_from' => now()->startOfMonth()->timestamp,
                'date_to' => now()->timestamp,
            ]);
            $this->turnover = [
                'income' => (int) ($turnoverResponse['total_income'] ?? $turnoverResponse['income'] ?? 0),
                'fees' => (int) ($turnoverResponse['total_fees'] ?? $turnoverResponse['fees'] ?? 0),
                'refunds' => (int) ($turnoverResponse['total_refunds'] ?? $turnoverResponse['refunds'] ?? 0),
                'net' => 0,
            ];
            $this->turnover['net'] = $this->turnover['income'] - $this->turnover['fees'] - $this->turnover['refunds'];

        } catch (Throwable $e) {
            $this->hasError = true;
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * @return list<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AccountBalanceWidget::class,
        ];
    }

    /**
     * @return list<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            AccountTurnoverWidget::class,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::ArrowPath)
                ->action(function (): void {
                    $this->loadFinancialData();
                    Notification::make()
                        ->title('Financial data refreshed')
                        ->success()
                        ->send();
                }),

            Action::make('view_statements')
                ->label('View Statements')
                ->icon(Heroicon::DocumentText)
                ->color('info')
                ->url(fn (): string => route('filament.admin.resources.company-statements.index')),
        ];
    }
}
