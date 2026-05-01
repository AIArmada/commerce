<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages;

use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Route;
use Override;

final class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Invoices';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'View and manage all billing invoices from Chip purchases.';
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): void {
                    // Export logic would go here
                }),

            Action::make('view_reports')
                ->label('View Reports')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->url(function (): string {
                    $panelId = filament()->getCurrentPanel()?->getId() ?? 'admin';
                    $routeName = "filament.{$panelId}.pages.cashier-chip-dashboard";

                    if (! Route::has($routeName)) {
                        return '#';
                    }

                    return route($routeName);
                }),
        ];
    }
}
