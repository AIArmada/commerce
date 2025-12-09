<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use BackedEnum;
use Exception;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

final class ViewInvoices extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament-cashier::customer-portal.view-invoices';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.invoices.title');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.invoices.title');
    }

    /**
     * @return Collection<int, array{id: string, gateway: string, number: string, amount: string, date: string, status: string, download_url: string|null}>
     */
    public function getInvoices(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $invoices = collect();
        $detector = app(GatewayDetector::class);

        // Get Stripe invoices
        if ($detector->isAvailable('stripe') && method_exists($user, 'invoices')) {
            try {
                $stripeInvoices = $user->invoices();

                foreach ($stripeInvoices as $invoice) {
                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'stripe',
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => $invoice->total(),
                        'date' => $invoice->date()->format('M d, Y'),
                        'status' => $invoice->paid ? 'paid' : 'open',
                        'download_url' => $invoice->invoicePdf(),
                    ]);
                }
            } catch (Exception) {
                // Silently fail if API is not configured
            }
        }

        // Get CHIP invoices
        if ($detector->isAvailable('chip') && method_exists($user, 'chipInvoices')) {
            try {
                $chipInvoices = $user->chipInvoices();

                foreach ($chipInvoices as $invoice) {
                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'chip',
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => 'RM ' . number_format(($invoice->amount ?? 0) / 100, 2),
                        'date' => $invoice->created_at?->format('M d, Y') ?? 'N/A',
                        'status' => $invoice->status ?? 'unknown',
                        'download_url' => $invoice->pdf_url ?? null,
                    ]);
                }
            } catch (Exception) {
                // Silently fail if API is not configured
            }
        }

        return $invoices->sortByDesc('date')->values();
    }
}
