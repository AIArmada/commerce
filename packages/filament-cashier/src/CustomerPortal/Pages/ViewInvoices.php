<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\Cashier\Support\CurrencyFormatter;
use AIArmada\Cashier\Support\GatewayDetector;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ViewInvoices extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 3;

    /** @var view-string */
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
                    $invoiceDate = $invoice->date();

                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'stripe',
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => $invoice->total(),
                        'date' => $invoiceDate->format('M d, Y'),
                        'status' => $invoice->paid ? 'paid' : 'open',
                        'download_url' => $invoice->invoicePdf(),
                        'sort_timestamp' => $invoiceDate->timestamp,
                    ]);
                }
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve Stripe invoices', ['error' => $e->getMessage()]);
            }
        }

        // Get CHIP invoices
        if ($detector->isAvailable('chip') && method_exists($user, 'chipInvoices')) {
            try {
                $chipInvoices = $user->chipInvoices();

                foreach ($chipInvoices as $invoice) {
                    $createdAt = $invoice->created_at;
                    $currency = (string) ($invoice->currency ?? config('cashier.currency', 'MYR'));

                    $invoices->push([
                        'id' => $invoice->id,
                        'gateway' => 'chip',
                        'number' => $invoice->number ?? $invoice->id,
                        'amount' => CurrencyFormatter::format((int) ($invoice->amount ?? 0), $currency),
                        'date' => $createdAt?->format('M d, Y') ?? 'N/A',
                        'status' => $invoice->status ?? 'unknown',
                        'download_url' => $invoice->pdf_url ?? null,
                        'sort_timestamp' => $createdAt?->timestamp ?? 0,
                    ]);
                }
            } catch (Throwable $e) {
                Log::debug('Failed to retrieve CHIP invoices', ['error' => $e->getMessage()]);
            }
        }

        /** @var Collection<int, array{id: string, gateway: string, number: string, amount: string, date: string, status: string, download_url: string|null}> $result */
        $result = $invoices
            ->sortByDesc('sort_timestamp')
            ->values()
            ->map(function (array $invoice): array {
                return [
                    'id' => (string) $invoice['id'],
                    'gateway' => (string) $invoice['gateway'],
                    'number' => (string) $invoice['number'],
                    'amount' => (string) $invoice['amount'],
                    'date' => (string) $invoice['date'],
                    'status' => (string) $invoice['status'],
                    'download_url' => is_string($invoice['download_url'] ?? null) ? $invoice['download_url'] : null,
                ];
            })
            ->values();

        // @phpstan-ignore-next-line Collection covariance false positive with exact array shape.
        return $result;
    }
}
